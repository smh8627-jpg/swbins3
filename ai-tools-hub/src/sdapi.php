<?php
/**
 * 로컬 Stable Diffusion WebUI(AUTOMATIC1111) API 연동.
 * 서버는 C:\swbins3\sd-webui 에서 webui-user.bat(--api)로 띄운다.
 */

define('AIHUB_SD_API_BASE', getenv('SD_API_URL') ?: 'http://127.0.0.1:7860');

/**
 * @return array{ok: bool, images?: string[], error?: string}
 */
function aihubGenerateImage(array $params): array
{
    $payload = [
        'prompt' => (string)($params['prompt'] ?? ''),
        'negative_prompt' => (string)($params['negative_prompt'] ?? ''),
        'width' => (int)($params['width'] ?? 512),
        'height' => (int)($params['height'] ?? 512),
        'steps' => (int)($params['steps'] ?? 20),
        'cfg_scale' => 7,
        'sampler_name' => 'Euler a',
        'seed' => (int)($params['seed'] ?? -1),
        'batch_size' => 1,
    ];

    if ($payload['prompt'] === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력해 주세요.'];
    }

    $ch = curl_init(AIHUB_SD_API_BASE . '/sdapi/v1/txt2img');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return ['ok' => false, 'error' => "로컬 이미지 생성 서버(sd-webui)에 연결할 수 없습니다: {$error}"];
    }

    $data = json_decode((string)$raw, true);

    if ($status !== 200 || !is_array($data)) {
        $detail = is_array($data) ? ($data['error'] ?? $data['detail'] ?? '') : substr((string)$raw, 0, 200);
        return ['ok' => false, 'error' => "생성 실패 (HTTP {$status}): {$detail}"];
    }

    if (empty($data['images'])) {
        return ['ok' => false, 'error' => '이미지가 생성되지 않았습니다. 체크포인트 모델이 로드되어 있는지 확인해 주세요.'];
    }

    return ['ok' => true, 'images' => $data['images']];
}

define('AIHUB_MOTION_MODULE', getenv('AIHUB_MOTION_MODULE') ?: 'mm_sd_v15_v2.safetensors');

/**
 * AnimateDiff 확장(extensions/sd-webui-animatediff)을 통한 짧은 영상 클립 생성.
 * @return array{ok: bool, video?: string, error?: string}
 */
function aihubGenerateVideo(array $params): array
{
    $prompt = (string)($params['prompt'] ?? '');
    if ($prompt === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력해 주세요.'];
    }

    $videoLength = max(8, min(32, (int)($params['video_length'] ?? 16)));

    $payload = [
        'prompt' => $prompt,
        'negative_prompt' => (string)($params['negative_prompt'] ?? ''),
        'width' => (int)($params['width'] ?? 512),
        'height' => (int)($params['height'] ?? 512),
        'steps' => (int)($params['steps'] ?? 20),
        'cfg_scale' => 7,
        'sampler_name' => 'Euler a',
        'seed' => -1,
        'batch_size' => 1,
        'alwayson_scripts' => [
            'AnimateDiff' => [
                'args' => [[
                    'model' => AIHUB_MOTION_MODULE,
                    'format' => ['MP4'],
                    'enable' => true,
                    'video_length' => $videoLength,
                    'fps' => (int)($params['fps'] ?? 8),
                    'loop_number' => 0,
                    'closed_loop' => 'R+P',
                    'batch_size' => 16,
                    'stride' => 1,
                    'overlap' => -1,
                ]],
            ],
        ],
    ];

    $ch = curl_init(AIHUB_SD_API_BASE . '/sdapi/v1/txt2img');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 600,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return ['ok' => false, 'error' => "로컬 영상 생성 서버(sd-webui)에 연결할 수 없습니다: {$error}"];
    }

    $data = json_decode((string)$raw, true);

    if ($status !== 200 || !is_array($data)) {
        $detail = is_array($data) ? ($data['error'] ?? $data['detail'] ?? '') : substr((string)$raw, 0, 200);
        return ['ok' => false, 'error' => "생성 실패 (HTTP {$status}): {$detail}"];
    }

    if (empty($data['images'])) {
        return ['ok' => false, 'error' => '영상이 생성되지 않았습니다. 체크포인트·모션 모듈이 준비되어 있는지 확인해 주세요.'];
    }

    return ['ok' => true, 'video' => $data['images'][0]];
}

define('AIHUB_MUSIC_API_BASE', getenv('MUSIC_API_URL') ?: 'http://127.0.0.1:7862');

/**
 * 로컬 MusicGen 서버(music-gen/server.py)를 통한 음악 생성.
 * @return array{ok: bool, audio?: string, error?: string}
 */
function aihubGenerateMusic(array $params): array
{
    $prompt = trim((string)($params['prompt'] ?? ''));
    if ($prompt === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력해 주세요.'];
    }

    $duration = max(3, min(30, (int)($params['duration'] ?? 8)));

    $ch = curl_init(AIHUB_MUSIC_API_BASE . '/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['prompt' => $prompt, 'duration' => $duration], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return ['ok' => false, 'error' => "로컬 음악 생성 서버(music-gen)에 연결할 수 없습니다: {$error}"];
    }

    $data = json_decode((string)$raw, true);

    if (!is_array($data) || empty($data['ok'])) {
        $detail = is_array($data) ? ($data['error'] ?? '') : substr((string)$raw, 0, 200);
        return ['ok' => false, 'error' => "생성 실패 (HTTP {$status}): {$detail}"];
    }

    return ['ok' => true, 'audio' => $data['audio']];
}

define('AIHUB_OLLAMA_API_BASE', getenv('OLLAMA_API_URL') ?: 'http://127.0.0.1:11434');
define('AIHUB_DOCUMENT_MODEL', getenv('AIHUB_DOCUMENT_MODEL') ?: 'qwen2.5:7b');

/**
 * 로컬 Ollama로 문서(글) 생성.
 * @return array{ok: bool, text?: string, error?: string}
 */
function aihubGenerateDocument(array $params): array
{
    $prompt = trim((string)($params['prompt'] ?? ''));
    if ($prompt === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력해 주세요.'];
    }

    $payload = [
        'model' => AIHUB_DOCUMENT_MODEL,
        'prompt' => $prompt,
        'stream' => false,
    ];

    $ch = curl_init(AIHUB_OLLAMA_API_BASE . '/api/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return ['ok' => false, 'error' => "로컬 문서 생성 서버(Ollama)에 연결할 수 없습니다: {$error}"];
    }

    $data = json_decode((string)$raw, true);

    if ($status !== 200 || !is_array($data)) {
        $detail = is_array($data) ? ($data['error'] ?? '') : substr((string)$raw, 0, 200);
        return ['ok' => false, 'error' => "생성 실패 (HTTP {$status}): {$detail}"];
    }

    if (isset($data['error'])) {
        return ['ok' => false, 'error' => $data['error']];
    }

    return ['ok' => true, 'text' => (string)($data['response'] ?? '')];
}

define('AIHUB_VOICE_API_BASE', getenv('VOICE_API_URL') ?: 'http://127.0.0.1:7863');

/**
 * 로컬 Coqui XTTS-v2 서버(voice-gen/server.py)를 통한 음성 생성.
 * @return array{ok: bool, audio?: string, error?: string}
 */
function aihubGenerateVoice(array $params): array
{
    $text = trim((string)($params['text'] ?? ''));
    if ($text === '') {
        return ['ok' => false, 'error' => '텍스트를 입력해 주세요.'];
    }

    $payload = [
        'text' => $text,
        'language' => (string)($params['language'] ?? 'ko'),
    ];
    if (!empty($params['speaker_wav'])) {
        $payload['speaker_wav'] = (string)$params['speaker_wav'];
    }

    $ch = curl_init(AIHUB_VOICE_API_BASE . '/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return ['ok' => false, 'error' => "로컬 음성 생성 서버(voice-gen)에 연결할 수 없습니다: {$error}"];
    }

    $data = json_decode((string)$raw, true);

    if (!is_array($data) || empty($data['ok'])) {
        $detail = is_array($data) ? ($data['error'] ?? '') : substr((string)$raw, 0, 200);
        return ['ok' => false, 'error' => $detail !== '' ? $detail : "생성 실패 (HTTP {$status})"];
    }

    return ['ok' => true, 'audio' => $data['audio']];
}

define('AIHUB_CODE_MODEL', getenv('AIHUB_CODE_MODEL') ?: 'qwen2.5-coder:7b');

/**
 * 로컬 Ollama(코딩 특화 모델)로 코드/앱 생성.
 * @return array{ok: bool, text?: string, error?: string}
 */
function aihubGenerateCode(array $params): array
{
    $prompt = trim((string)($params['prompt'] ?? ''));
    if ($prompt === '') {
        return ['ok' => false, 'error' => '프롬프트를 입력해 주세요.'];
    }

    $payload = [
        'model' => AIHUB_CODE_MODEL,
        'prompt' => $prompt,
        'system' => '너는 코딩 도우미다. 요청받은 앱·코드를 바로 실행 가능한 완결된 형태로, 코드 블록과 짧은 설명만으로 답한다.',
        'stream' => false,
    ];

    $ch = curl_init(AIHUB_OLLAMA_API_BASE . '/api/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return ['ok' => false, 'error' => "로컬 코드 생성 서버(Ollama)에 연결할 수 없습니다: {$error}"];
    }

    $data = json_decode((string)$raw, true);

    if ($status !== 200 || !is_array($data)) {
        $detail = is_array($data) ? ($data['error'] ?? '') : substr((string)$raw, 0, 200);
        return ['ok' => false, 'error' => "생성 실패 (HTTP {$status}): {$detail}"];
    }

    if (isset($data['error'])) {
        return ['ok' => false, 'error' => $data['error']];
    }

    return ['ok' => true, 'text' => (string)($data['response'] ?? '')];
}
