# -*- coding: utf-8 -*-
"""
로컬 MusicGen API 서버. ai-tools-hub(PHP)가 이 서버(기본 포트 7862)로 요청을 보낸다.
모델은 최초 요청 시 한 번만 로드해서 메모리에 올려둔다.
"""
import base64
import io

import scipy.io.wavfile
import torch
from flask import Flask, jsonify, request
from transformers import AutoProcessor, MusicgenForConditionalGeneration

MODEL_NAME = "facebook/musicgen-small"
DEVICE = "cuda" if torch.cuda.is_available() else "cpu"

app = Flask(__name__)
_state = {"processor": None, "model": None}


def get_model():
    if _state["model"] is None:
        _state["processor"] = AutoProcessor.from_pretrained(MODEL_NAME)
        _state["model"] = MusicgenForConditionalGeneration.from_pretrained(MODEL_NAME).to(DEVICE)
    return _state["processor"], _state["model"]


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
