# 라데온(AMD) GPU에서 돌리기 — 참고용 (이 세션에서 실제 AMD 장비로 검증하지 못했습니다)

`SETUP_GUIDE.md`는 전부 NVIDIA CUDA 전용으로 만들어졌습니다. AMD Radeon GPU는 Windows에서
CUDA를 못 쓰기 때문에 몇 군데를 다르게 가야 합니다. 아래는 알려진 방법을 정리한 것이고,
**실제 라데온 장비에서 이 세션이 직접 실행/검증하지는 못했습니다** — 진행하다 막히면 그때 같이 고치면 됩니다.

## 왜 그대로 안 되는지

- PyTorch의 ROCm(AMD GPU 가속) 빌드는 **Linux만** 지원합니다. Windows용 ROCm PyTorch 휠은 없습니다.
- 그래서 Windows + Radeon 조합에서 GPU를 쓰려면 아래 둘 중 하나를 골라야 합니다.

## 옵션 A — DirectML (공식, 안전하지만 느림)

Microsoft의 DirectML을 통해 DirectX 12로 도는 방식. 설정은 간단하지만 CUDA보다 훨씬 느리고,
일부 연산은 지원이 안 돼서 자동으로 CPU로 떨어지기도 합니다.

**music-gen / voice-gen (일반 PyTorch 스크립트)**

```powershell
# torch 대신 torch-directml 설치 (torch 자체는 CPU 버전으로)
venv\Scripts\python.exe -m pip install torch torch-directml
```

`server.py`에서 아래처럼 device 판별 부분을 바꿔야 합니다(현재는 `cuda`만 봄):

```python
try:
    import torch_directml
    DEVICE = torch_directml.device()
except ImportError:
    DEVICE = "cuda" if torch.cuda.is_available() else "cpu"
```

**sd-webui (이미지·동영상)**

원본 AUTOMATIC1111 대신, AMD/DirectML을 공식 지원하는 포크를 씁니다:

```powershell
git clone --depth 1 https://github.com/lshqqytiger/stable-diffusion-webui-amdgpu.git sd-webui
```

`webui-user.bat`에 `--use-directml` 옵션 추가:

```bat
set COMMANDLINE_ARGS=--api --use-directml
```

나머지(레포 미러 교체, AnimateDiff 확장 등)는 `SETUP_GUIDE.md`와 동일합니다.

## 옵션 B — ZLUDA (비공식, 훨씬 빠르지만 설정이 더 복잡함)

CUDA 호출을 라데온에서 그대로 돌려주는 비공식 번역 레이어입니다. 최신 RDNA2/3 카드에서
DirectML보다 체감상 훨씬 빠르지만(거의 네이티브급), 공식 지원이 아니라 웹UI 업데이트에
깨질 수 있습니다. 위 포크(`lshqqytiger/stable-diffusion-webui-amdgpu`)가 ZLUDA 설치 스크립트도
같이 제공합니다 — 저장소의 `webui-user.bat` 안내와 README(ZLUDA 섹션)를 그대로 따라가면 됩니다.
음악·음성 서버(music-gen/voice-gen)까지 ZLUDA로 돌리는 건 별도 설정이 필요해서 권장하지 않고,
그 둘은 옵션 A(DirectML)나 CPU로 돌리는 걸 추천합니다.

## 옵션 C — 그냥 CPU로

아무 설정 없이 되지만 이미지 하나에 수 분, 음악/음성은 그보다 더 걸릴 수 있습니다.
GPU 세팅이 막힐 때 임시로 확인용으로만 쓰는 걸 권장합니다.

## 정리

| 구성 | 이미지/동영상(sd-webui) | 음악/음성(music-gen, voice-gen) |
|---|---|---|
| 추천 | `lshqqytiger/stable-diffusion-webui-amdgpu` (ZLUDA 또는 `--use-directml`) | `torch-directml` |
| VRAM 부족하거나 안 될 때 | `--use-directml` 로 다운그레이드 | CPU (느림) |

실제로 진행해보시고, 설치 중 에러 메시지를 그대로 알려주시면 그 카드/드라이버 조합에 맞게 같이 고쳐드리겠습니다.
