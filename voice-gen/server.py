# -*- coding: utf-8 -*-
"""
로컬 Coqui XTTS-v2 음성 생성 API 서버. ai-tools-hub(PHP)가 이 서버(기본 포트 7863)로 요청을 보낸다.
XTTS는 목소리를 흉내낼 짧은 참조 음성(레퍼런스 wav)이 필요하다.
처음 요청에서 참조 음성을 업로드하면 reference.wav로 저장해서 이후 계속 재사용한다.
VRAM이 넉넉하지 않아(sd-webui·music-gen·3d-gen과 동시에 켜두는 경우가 많음) 일정 시간
미사용 시 자동으로 GPU 메모리에서 내린다(IDLE_UNLOAD_SECONDS, 기본 5분).
"""
import base64
import gc
import os
import threading
import time

import torch
from flask import Flask, jsonify, request
from TTS.api import TTS

MODEL_NAME = "tts_models/multilingual/multi-dataset/xtts_v2"
DEVICE = "cuda" if torch.cuda.is_available() else "cpu"
REFERENCE_PATH = os.path.join(os.path.dirname(__file__), "reference.wav")
OUTPUT_PATH = os.path.join(os.path.dirname(__file__), "_last_output.wav")
_LOCAL_MODEL_DIR = os.path.join(os.path.dirname(__file__), "models", "xtts_v2")
_LOCAL_CONFIG_PATH = os.path.join(_LOCAL_MODEL_DIR, "config.json")
IDLE_UNLOAD_SECONDS = int(os.environ.get("IDLE_UNLOAD_SECONDS", "300"))

app = Flask(__name__)
_lock = threading.Lock()
_state = {"tts": None, "last_used": 0.0}


def get_tts():
    with _lock:
        if _state["tts"] is None:
            if os.path.isfile(_LOCAL_CONFIG_PATH):
                _state["tts"] = TTS(model_path=_LOCAL_MODEL_DIR, config_path=_LOCAL_CONFIG_PATH).to(DEVICE)
            else:
                _state["tts"] = TTS(MODEL_NAME).to(DEVICE)
        _state["last_used"] = time.time()
        return _state["tts"]


def _unload_tts():
    with _lock:
        if _state["tts"] is None:
            return
        _state["tts"] = None
    gc.collect()
    if DEVICE == "cuda":
        torch.cuda.empty_cache()


def _idle_unload_watcher():
    while True:
        time.sleep(30)
        if _state["tts"] is not None and DEVICE == "cuda" and \
                time.time() - _state["last_used"] >= IDLE_UNLOAD_SECONDS:
            _unload_tts()


threading.Thread(target=_idle_unload_watcher, daemon=True).start()


@app.post("/generate")
def generate():
    body = request.get_json(silent=True) or {}
    text = (body.get("text") or "").strip()
    language = (body.get("language") or "ko").strip()
    speaker_wav_b64 = body.get("speaker_wav")

    if not text:
        return jsonify(ok=False, error="텍스트를 입력해 주세요."), 400

    if speaker_wav_b64:
        try:
            with open(REFERENCE_PATH, "wb") as f:
                f.write(base64.b64decode(speaker_wav_b64))
        except Exception as exc:  # noqa: BLE001
            return jsonify(ok=False, error=f"참조 음성 저장 실패: {exc}"), 400

    if not os.path.isfile(REFERENCE_PATH):
        return jsonify(
            ok=False,
            error="참조 음성(목소리 샘플)이 없습니다. 5~10초 정도의 깨끗한 목소리 wav 파일을 먼저 업로드해 주세요.",
        ), 400

    try:
        tts = get_tts()
    except Exception as exc:  # noqa: BLE001
        return jsonify(ok=False, error=f"모델 로드 실패: {exc}"), 500

    try:
        tts.tts_to_file(text=text, speaker_wav=REFERENCE_PATH, language=language, file_path=OUTPUT_PATH)
        with open(OUTPUT_PATH, "rb") as f:
            audio_b64 = base64.b64encode(f.read()).decode("ascii")
        _state["last_used"] = time.time()
        return jsonify(ok=True, audio=audio_b64)
    except torch.cuda.OutOfMemoryError:
        return jsonify(ok=False, error="GPU 메모리가 부족합니다. 텍스트 길이를 줄여서 다시 시도해 주세요."), 500
    except Exception as exc:  # noqa: BLE001
        return jsonify(ok=False, error=f"생성 실패: {exc}"), 500


@app.get("/health")
def health():
    return jsonify(ok=True, device=DEVICE, model_loaded=_state["tts"] is not None,
                    has_reference=os.path.isfile(REFERENCE_PATH))


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=7863)
