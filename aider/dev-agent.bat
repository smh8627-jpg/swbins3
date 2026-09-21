@echo off
rem Coding agent (Aider). Default: local Ollama qwen2.5-coder:7b. Pass a model name to override.
rem Usage: run this from the target project folder (git repo recommended, aider auto-commits changes)
rem
rem Examples:
rem   dev-agent.bat                              -> default (local qwen2.5-coder:7b)
rem   dev-agent.bat ollama_chat/qwen2.5-coder:14b -> bigger local model (needs more VRAM)
rem   dev-agent.bat gemini/gemini-2.0-flash       -> free-tier cloud (set GEMINI_API_KEY first, see README)
set MODEL=%1
if "%MODEL%"=="" set MODEL=ollama_chat/qwen2.5-coder:7b
set OLLAMA_API_BASE=http://127.0.0.1:11434
"%~dp0venv\Scripts\aider.exe" --model %MODEL%
