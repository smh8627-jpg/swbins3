# -*- coding: utf-8 -*-
"""
로컬 MusicGen API 서버. ai-tools-hub(PHP)가 이 서버(기본 포트 7862)로 요청을 보낸다.
모델은 최초 요청 시 한 번만 로드해서 메모리에 올려둔다.
VRAM이 넉넉하지 않아(sd-webui·voice-gen·3d-gen과 동시에 켜두는 경우가 많음) 일정 시간
미사용 시 자동으로 GPU 메모리에서 내린다(IDLE_UNLOAD_SECONDS, 기본 5분).
"""
import base64
import gc
import io
import os
import threading
import time

import scipy.io.wavfile
import torch
from flask import Flask, jsonify, request
from transformers import AutoProcessor, MusicgenForConditionalGeneration

_LOCAL_MODEL_DIR = os.path.join(os.path.dirname(__file__), "models", "musicgen-small")
MODEL_NAME = _LOCAL_MODEL_DIR if os.path.isdir(_LOCAL_MODEL_DIR) else "facebook/musicgen-small"
DEVICE = "cuda" if torch.cuda.is_available() else "cpu"
IDLE_UNLOAD_SECONDS = int(os.environ.get("IDLE_UNLOAD_SECONDS", "300"))

app = Flask(__name__)
_lock = threading.Lock()
_state = {"processor": None, "model": None, "last_used": 0.0}


def get_model():
    with _lock:
        if _state["model"] is None:
            _state["processor"] = AutoProcessor.from_pretrained(MODEL_NAME)
            _state["model"] = MusicgenForConditionalGeneration.from_pretrained(MODEL_NAME).to(DEVICE)
        _state["last_used"] = time.time()
        return _state["processor"], _state["model"]


def _unload_model():
    with _lock:
        if _state["model"] is None:
            return
        _state["processor"] = None
        _state["model"] = None
    gc.collect()
    if DEVICE == "cuda":
        torch.cuda.empty_cache()


def _idle_unload_watcher():
    while True:
        time.sleep(30)
        if _state["model"] is not None and \
                time.time() - _state["last_used"] >= IDLE_UNLOAD_SECONDS:
            _unload_model()


threading.Thread(target=_idle_unload_watcher, daemon=True).start()


@app.post("/generate")
def generate():
    body = request.get_json(silent=True) or {}
    prompt = (body.get("prompt") or "").strip()
    duration = max(3, min(30, int(body.get("duration") or 8)))

    if not prompt:
        return jsonify(ok=False, error="프롬프트를 입력해 주세요."), 400

    try:
        processor, model = get_model()
    except Exception as exc:  # noqa: BLE001
        return jsonify(ok=False, error=f"모델 로드 실패: {exc}"), 500

    try:
        inputs = processor(text=[prompt], padding=True, return_tensors="pt").to(DEVICE)
        # MusicGen 오디오 코드북은 초당 약 50 프레임.
        max_new_tokens = duration * 50
        with torch.no_grad():
            audio_values = model.generate(**inputs, max_new_tokens=max_new_tokens)
        sampling_rate = model.config.audio_encoder.sampling_rate
        audio = audio_values[0, 0].cpu().numpy()

        buf = io.BytesIO()
        scipy.io.wavfile.write(buf, rate=sampling_rate, data=audio)
        wav_b64 = base64.b64encode(buf.getvalue()).decode("ascii")
        _state["last_used"] = time.time()
        return jsonify(ok=True, audio=wav_b64)
    except torch.cuda.OutOfMemoryError:
        return jsonify(ok=False, error="GPU 메모리가 부족합니다. 길이를 줄여서 다시 시도해 주세요."), 500
    except Exception as exc:  # noqa: BLE001
        return jsonify(ok=False, error=f"생성 실패: {exc}"), 500


@app.get("/health")
def health():
    return jsonify(ok=True, device=DEVICE, model_loaded=_state["model"] is not None)


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=7862)
