# -*- coding: utf-8 -*-
"""
로컬 Coqui XTTS-v2 음성 생성 API 서버. ai-tools-hub(PHP)가 이 서버(기본 포트 7863)로 요청을 보낸다.
XTTS는 목소리를 흉내낼 짧은 참조 음성(레퍼런스 wav)이 필요하다.
처음 요청에서 참조 음성을 업로드하면 reference.wav로 저장해서 이후 계속 재사용한다.
"""
import base64
import os

import torch
from flask import Flask, jsonify, request
from TTS.api import TTS

MODEL_NAME = "tts_models/multilingual/multi-dataset/xtts_v2"
DEVICE = "cuda" if torch.cuda.is_available() else "cpu"
REFERENCE_PATH = os.path.join(os.path.dirname(__file__), "reference.wav")
OUTPUT_PATH = os.path.join(os.path.dirname(__file__), "_last_output.wav")

app = Flask(__name__)
_state = {"tts": None}


def get_tts():
    if _state["tts"] is None:
        _state["tts"] = TTS(MODEL_NAME).to(DEVICE)
    return _state["tts"]


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
