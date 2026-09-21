@echo off
rem 로컬 Ollama(qwen2.5-coder:7b)를 파일 읽기/쓰기/git 커밋까지 하는 코딩 에이전트(Aider)로 실행.
rem 사용법: 작업할 프로젝트 폴더에서 이 배치파일을 실행 (git 저장소여야 함, 아니면 aider가 자동으로 git init 여부를 물어봄)
set OLLAMA_API_BASE=http://127.0.0.1:11434
"%~dp0venv\Scripts\aider.exe" --model ollama_chat/qwen2.5-coder:7b %*
