<?php
/**
 * 메인 페이지 HTML 렌더링. Slim 라우트에서 호출.
 */

require_once __DIR__ . '/data.php';

function aihubRenderHome(array $tools, array $tips): string
{
    $categories = array_keys($tools);
    $firstKey = $categories[0] ?? '';

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI 도구 모음 · swbins3</title>
<style>
  * { box-sizing: border-box; }
  :root {
    --bg: #eef1f5; --card: #fff; --card-border: #d7dee6;
    --text: #1f2937; --muted: #5b6b7c; --faint: #8b98a6;
    --accent: #1d5ea8; --up: #1a7f37; --down: #b3261e;
    --topbar: #16324f; --topbar-line: #0e2338; --topbar-text: #eef3f8;
    --badge: #eef3f8;
  }
  body { margin: 0; padding: 0; background: var(--bg); color: var(--text);
         font-family: "Pretendard", "Malgun Gothic", -apple-system, sans-serif; }
  header { background: var(--topbar); border-bottom: 3px solid var(--topbar-line); }
  .header-inner { max-width: 1080px; margin: 0 auto; padding: 14px 24px; }
  header h1 { margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--topbar-text); }
  .wrap { max-width: 1080px; margin: 0 auto; padding: 20px 24px 40px; }
  .tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
  .tab { padding: 9px 16px; border: 1px solid var(--card-border); border-radius: 999px; font-size: 13px;
         font-weight: 700; cursor: pointer; background: #fff; color: var(--muted); user-select: none; }
  .tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }
  .tab .count { opacity: .7; font-weight: 400; }
  .search { width: 100%; padding: 10px 14px; margin-bottom: 14px; border: 1px solid var(--card-border);
            border-radius: 6px; font-size: 13px; font-family: inherit; background: #fff; color: var(--text); }
  .panel { display: none; }
  .panel.active { display: block; }
  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
  .card { background: var(--card); border: 1px solid var(--card-border); border-radius: 6px;
          padding: 16px 18px; transition: box-shadow .15s, transform .15s; }
  .card:hover { box-shadow: 0 4px 14px rgba(22, 50, 79, .1); transform: translateY(-1px); }
  .card[hidden], .tip[hidden] { display: none; }
  .empty { font-size: 13px; color: var(--faint); padding: 8px 2px; }
  .card h3 { margin: 0 0 6px; font-size: 1rem; }
  .card h3 a { color: var(--text); text-decoration: none; }
  .card h3 a:hover { color: var(--accent); text-decoration: underline; }
  .card p { margin: 0 0 10px; font-size: 13px; color: var(--muted); line-height: 1.5; }
  .badge { display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px;
           background: var(--badge); color: var(--accent); margin-right: 6px; }
  .tag { display: inline-block; font-size: 11px; color: var(--faint); margin-right: 6px; }
  .tips h2 { font-size: .9rem; color: var(--muted); text-transform: uppercase; letter-spacing: .06em;
             margin: 24px 0 12px; }
  .tips h2:first-child { margin-top: 0; }
  .tip { background: var(--card); border: 1px solid var(--card-border); border-radius: 6px;
         padding: 12px 16px; margin-bottom: 10px; }
  .tip b { display: block; font-size: 13.5px; margin-bottom: 4px; }
  .tip span { font-size: 13px; color: var(--muted); line-height: 1.5; }
  .hint { font-size: 12px; color: var(--faint); margin: 16px 0 0; line-height: 1.6; }
  .gen-box { background: var(--card); border: 1px solid var(--card-border); border-radius: 6px; padding: 18px 20px; }
  .gen-box label { display: block; font-size: 12.5px; font-weight: 700; color: var(--muted); margin: 12px 0 6px; }
  .gen-box label:first-child { margin-top: 0; }
  .gen-box textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--card-border); border-radius: 6px;
                       font-family: inherit; font-size: 13px; resize: vertical; color: var(--text); }
  .gen-row { display: flex; gap: 14px; align-items: flex-end; margin-top: 12px; flex-wrap: wrap; }
  .gen-row label { display: flex; flex-direction: column; gap: 4px; font-size: 12.5px; font-weight: 700; color: var(--muted); margin: 0; }
  .gen-row select, .gen-row input[type=number] { padding: 7px 10px; border: 1px solid var(--card-border); border-radius: 6px;
                       font-family: inherit; font-size: 13px; }
  .gen-row input[type=number] { width: 70px; }
  .gen-row input[type=file] { font-size: 12px; }
  #gen-submit { padding: 9px 20px; border: none; border-radius: 6px; background: var(--accent); color: #fff;
                font-weight: 700; font-size: 13px; cursor: pointer; }
  #gen-submit:disabled { opacity: .6; cursor: not-allowed; }
  .gen-status { margin-top: 14px; font-size: 13px; }
  .gen-status.error { color: var(--down); }
  .gen-status.loading { color: var(--muted); }
  .gen-result { margin-top: 14px; }
  .gen-result img, .gen-result video { max-width: 100%; border-radius: 6px; border: 1px solid var(--card-border); display: block; }
  .gen-result .doc-text { white-space: pre-wrap; font-size: 13.5px; line-height: 1.6; background: var(--bg);
                           border: 1px solid var(--card-border); border-radius: 6px; padding: 14px 16px; }
  .gen-result .code-text { white-space: pre-wrap; font-family: "Consolas", "D2Coding", monospace; font-size: 13px;
                            line-height: 1.6; background: #1e2530; color: #dbe4ee; border-radius: 6px;
                            padding: 14px 16px; overflow-x: auto; }
  .status-list { margin-top: 14px; display: flex; flex-direction: column; gap: 8px; }
  .status-row { display: flex; align-items: center; gap: 10px; font-size: 13.5px; padding: 10px 14px;
                 border: 1px solid var(--card-border); border-radius: 6px; background: var(--bg); }
  .status-dot { width: 10px; height: 10px; border-radius: 50%; flex: 0 0 auto; background: var(--faint); }
  .status-dot.up { background: var(--up); }
  .status-dot.down { background: var(--down); }
  .status-row .status-label { flex: 1 1 auto; }
  .status-row .status-text { font-size: 12px; font-weight: 700; }
  .status-row .status-text.up { color: var(--up); }
  .status-row .status-text.down { color: var(--down); }
  .legend { display: flex; gap: 16px; flex-wrap: wrap; align-items: baseline; font-size: 12px; color: var(--muted);
            margin: 0 0 16px; padding: 10px 14px; background: var(--card); border: 1px solid var(--card-border);
            border-radius: 6px; }
  .legend b { color: var(--text); }
  a { color: var(--accent); }
</style>
</head>
<body>
<header>
  <div class="header-inner">
    <h1>🧰 AI 도구 모음</h1>
  </div>
</header>
<div class="wrap">

  <div class="tabs">
<?php foreach ($tools as $key => $cat) { ?>
    <div class="tab<?php echo $key === $firstKey ? ' active' : ''; ?>" data-tab="<?php echo aihubEsc($key); ?>">
      <?php echo aihubEsc($cat['icon'] ?? ''); ?> <?php echo aihubEsc($cat['label'] ?? $key); ?>
      <span class="count">(<?php echo count($cat['tools']); ?>)</span>
    </div>
<?php } ?>
    <div class="tab" data-tab="image-gen">🖼️ 이미지 생성</div>
    <div class="tab" data-tab="video-gen">🎬 동영상 생성</div>
    <div class="tab" data-tab="music-gen">🎵 음악 생성</div>
    <div class="tab" data-tab="doc-gen">📝 문서 생성</div>
    <div class="tab" data-tab="code-gen">💻 코드/앱 생성</div>
    <div class="tab" data-tab="voice-gen">🔊 음성 생성</div>
    <div class="tab" data-tab="status">🩺 서버 상태</div>
    <div class="tab" data-tab="token-saving">💡 토큰 절약법</div>
  </div>

  <input type="text" class="search" id="search" placeholder="도구 이름·설명·태그로 검색…">

  <div class="legend">
    <span><b>배지 기준</b></span>
    <span><span class="badge">무료</span> 유료 요금제 없이 계속 무료로 쓸 수 있음 (오픈소스·완전 무료 플랜)</span>
    <span><span class="badge">부분무료</span> 무료로 체험/제한적 사용 가능하고, 그 이상 쓰려면 유료 구독·크레딧 결제 필요</span>
    <span><span class="badge">유료</span> 무료 체험이 아예 없거나 사실상 유료로만 쓸 수 있음</span>
  </div>

<?php foreach ($tools as $key => $cat) { ?>
  <div class="panel<?php echo $key === $firstKey ? ' active' : ''; ?>" id="panel-<?php echo aihubEsc($key); ?>">
    <div class="grid">
<?php foreach ($cat['tools'] as $tool) { ?>
      <div class="card">
        <h3><a href="<?php echo aihubEsc($tool['url']); ?>" target="_blank" rel="noopener"><?php echo aihubEsc($tool['name']); ?></a></h3>
        <p><?php echo aihubEsc($tool['description']); ?></p>
        <div>
          <span class="badge"><?php echo aihubEsc($tool['pricing']); ?></span>
<?php foreach (($tool['tags'] ?? []) as $tag) { ?>
          <span class="tag">#<?php echo aihubEsc($tag); ?></span>
<?php } ?>
        </div>
      </div>
<?php } ?>
    </div>
  </div>
<?php } ?>

  <div class="panel" id="panel-image-gen">
    <div class="gen-box">
      <label for="gen-prompt">프롬프트 (영어일수록 결과가 좋습니다)</label>
      <textarea id="gen-prompt" rows="3" placeholder="예: a cozy cabin in a snowy forest, warm lighting, digital painting"></textarea>
      <label for="gen-negative">네거티브 프롬프트 (선택, 빼고 싶은 요소)</label>
      <textarea id="gen-negative" rows="2" placeholder="예: blurry, low quality, watermark"></textarea>
      <div class="gen-row">
        <label>크기
          <select id="gen-size">
            <option value="512x512" selected>512×512</option>
            <option value="768x512">768×512 (가로형)</option>
            <option value="512x768">512×768 (세로형)</option>
          </select>
        </label>
        <label>스텝<input type="number" id="gen-steps" value="20" min="1" max="50"></label>
        <button id="gen-submit" type="button">이미지 생성</button>
      </div>
      <div class="gen-status" id="gen-status" hidden></div>
      <div class="gen-result" id="gen-result"></div>
      <p class="hint">
        로컬 PC에 설치된 Stable Diffusion(sd-webui)으로 생성합니다. 외부로 전송되지 않고 이 PC에서만 동작하며,
        서버가 꺼져 있거나 모델이 없으면 오류가 표시됩니다.
      </p>
    </div>
  </div>

  <div class="panel" id="panel-video-gen">
    <div class="gen-box">
      <label for="vid-prompt">프롬프트 (영어일수록 결과가 좋습니다)</label>
      <textarea id="vid-prompt" rows="3" placeholder="예: a cat walking on a beach, waves, sunset, smooth motion"></textarea>
      <label for="vid-negative">네거티브 프롬프트 (선택)</label>
      <textarea id="vid-negative" rows="2" placeholder="예: blurry, low quality, watermark"></textarea>
      <div class="gen-row">
        <label>프레임 수<input type="number" id="vid-length" value="16" min="8" max="32"></label>
        <label>FPS<input type="number" id="vid-fps" value="8" min="4" max="16"></label>
        <label>스텝<input type="number" id="vid-steps" value="20" min="1" max="50"></label>
        <button id="vid-submit" type="button">동영상 생성</button>
      </div>
      <div class="gen-status" id="vid-status" hidden></div>
      <div class="gen-result" id="vid-result"></div>
      <p class="hint">
        AnimateDiff(로컬 sd-webui 확장)로 512×512, 짧은 클립(8~32프레임)을 생성합니다. GPU VRAM이 6GB급이라
        해상도를 높이거나 프레임을 너무 늘리면 메모리 부족 오류가 날 수 있습니다. 생성에 1~수 분 걸릴 수 있습니다.
      </p>
    </div>
  </div>

  <div class="panel" id="panel-music-gen">
    <div class="gen-box">
      <label for="mus-prompt">프롬프트 (영어일수록 결과가 좋습니다)</label>
      <textarea id="mus-prompt" rows="3" placeholder="예: lo-fi hip hop beat with soft piano and rain sounds"></textarea>
      <div class="gen-row">
        <label>길이(초)<input type="number" id="mus-duration" value="8" min="3" max="30"></label>
        <button id="mus-submit" type="button">음악 생성</button>
      </div>
      <div class="gen-status" id="mus-status" hidden></div>
      <div class="gen-result" id="mus-result"></div>
      <p class="hint">
        로컬 MusicGen(facebook/musicgen-small)으로 생성합니다. 처음 실행할 때만 모델을 메모리에 올리느라
        조금 더 걸리고, 그 다음부터는 빨라집니다. 길이가 길수록 생성 시간도 늘어납니다.
      </p>
    </div>
  </div>

  <div class="panel" id="panel-doc-gen">
    <div class="gen-box">
      <label for="doc-prompt">프롬프트 (한글로 써도 됩니다)</label>
      <textarea id="doc-prompt" rows="4" placeholder="예: 신제품 출시 안내 이메일을 정중한 어투로 작성해줘"></textarea>
      <div class="gen-row">
        <button id="doc-submit" type="button">문서 생성</button>
      </div>
      <div class="gen-status" id="doc-status" hidden></div>
      <div class="gen-result" id="doc-result"></div>
      <p class="hint">
        로컬 Ollama(qwen2.5:7b)로 생성합니다. 외부로 전송되지 않고 이 PC에서만 동작하며,
        모델이 크기 때문에 첫 응답까지 시간이 걸릴 수 있습니다.
      </p>
    </div>
  </div>

  <div class="panel" id="panel-code-gen">
    <div class="gen-box">
      <label for="code-prompt">프롬프트 (한글로 써도 됩니다)</label>
      <textarea id="code-prompt" rows="4" placeholder="예: 할 일을 추가·삭제할 수 있는 간단한 투두리스트 웹앱을 HTML 한 파일로 만들어줘"></textarea>
      <div class="gen-row">
        <button id="code-submit" type="button">코드 생성</button>
      </div>
      <div class="gen-status" id="code-status" hidden></div>
      <div class="gen-result" id="code-result"></div>
      <p class="hint">
        로컬 Ollama(qwen2.5-coder:7b)로 코드를 텍스트로 생성합니다. Bolt.new/v0처럼 바로 실행·미리보기까지
        해주는 건 아니고, 생성된 코드를 직접 파일로 저장해서 실행해야 합니다.
      </p>
    </div>
  </div>

  <div class="panel" id="panel-voice-gen">
    <div class="gen-box">
      <label for="voice-text">텍스트 (읽어줄 문장)</label>
      <textarea id="voice-text" rows="3" placeholder="예: 안녕하세요, 오늘 회의는 3시에 시작합니다."></textarea>
      <div class="gen-row">
        <label>언어
          <select id="voice-lang">
            <option value="ko" selected>한국어</option>
            <option value="en">영어</option>
            <option value="ja">일본어</option>
          </select>
        </label>
        <label>목소리 샘플(wav/mp3, 5~10초)<input type="file" id="voice-ref" accept="audio/*"></label>
        <button id="voice-submit" type="button">음성 생성</button>
      </div>
      <div class="gen-status" id="voice-status" hidden></div>
      <div class="gen-result" id="voice-result"></div>
      <p class="hint">
        로컬 Coqui XTTS-v2로 생성합니다. 처음 한 번은 목소리 샘플을 업로드해야 하고, 그 다음부터는
        같은 목소리로 계속 재사용됩니다(서버에 저장됨). 다른 목소리로 바꾸려면 새 샘플을 다시 올리면 됩니다.
      </p>
    </div>
  </div>

  <div class="panel" id="panel-status">
    <div class="gen-box">
      <div class="gen-row">
        <button id="status-refresh" type="button">상태 확인</button>
      </div>
      <div id="status-list" class="status-list"></div>
      <p class="hint">
        생성 기능이 안 될 때 여기서 어떤 서버가 꺼져 있는지 먼저 확인하세요.
        전체를 한 번에 켜려면 <code>C:\swbins3\start-all.bat</code>을 실행하면 됩니다(처음엔 모델 로딩으로 1~2분 걸릴 수 있습니다).
        끄려면 <code>stop-all.bat</code>을 실행하세요.
      </p>
    </div>
  </div>

  <div class="panel" id="panel-token-saving">
    <div class="tips">
<?php foreach ($tips as $group) { ?>
      <h2><?php echo aihubEsc($group['label'] ?? ''); ?></h2>
<?php foreach ($group['tips'] as $tip) { ?>
      <div class="tip">
        <b><?php echo aihubEsc($tip['title']); ?></b>
        <span><?php echo aihubEsc($tip['detail']); ?></span>
      </div>
<?php } ?>
<?php } ?>
    </div>
  </div>

  <div class="hint">
    링크는 잘 알려진 서비스 기준으로 정리했지만, 서비스 개편으로 주소가 바뀔 수 있습니다. 안 열리는 링크는 <code>data/tools.json</code> 에서 바로 고치면 됩니다.
  </div>
</div>
<script>
document.querySelectorAll('.tab').forEach(function (tab) {
  tab.addEventListener('click', function () {
    document.querySelectorAll('.tab').forEach(function (t) { t.classList.remove('active'); });
    document.querySelectorAll('.panel').forEach(function (p) { p.classList.remove('active'); });
    tab.classList.add('active');
    var panel = document.getElementById('panel-' + tab.dataset.tab);
    if (panel) { panel.classList.add('active'); }
  });
});

document.getElementById('search').addEventListener('input', function (e) {
  var q = e.target.value.trim().toLowerCase();
  document.querySelectorAll('.panel').forEach(function (panel) {
    var items = panel.querySelectorAll('.card, .tip');
    var visibleCount = 0;
    items.forEach(function (item) {
      var match = q === '' || item.textContent.toLowerCase().indexOf(q) !== -1;
      item.hidden = !match;
      if (match) { visibleCount++; }
    });
    var empty = panel.querySelector('.empty');
    if (items.length && visibleCount === 0) {
      if (!empty) {
        empty = document.createElement('div');
        empty.className = 'empty';
        empty.textContent = '검색 결과가 없습니다.';
        panel.appendChild(empty);
      }
      empty.hidden = false;
    } else if (empty) {
      empty.hidden = true;
    }
  });
});

document.getElementById('gen-submit').addEventListener('click', function () {
  var btn = this;
  var statusEl = document.getElementById('gen-status');
  var resultEl = document.getElementById('gen-result');
  var prompt = document.getElementById('gen-prompt').value.trim();
  var negative = document.getElementById('gen-negative').value.trim();
  var size = document.getElementById('gen-size').value.split('x');
  var steps = parseInt(document.getElementById('gen-steps').value, 10) || 20;

  if (!prompt) {
    statusEl.hidden = false;
    statusEl.className = 'gen-status error';
    statusEl.textContent = '프롬프트를 입력해 주세요.';
    return;
  }

  btn.disabled = true;
  statusEl.hidden = false;
  statusEl.className = 'gen-status loading';
  statusEl.textContent = '생성 중입니다… (10~30초 정도 걸릴 수 있습니다)';
  resultEl.innerHTML = '';

  fetch('/generate/image', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      prompt: prompt,
      negative_prompt: negative,
      width: parseInt(size[0], 10),
      height: parseInt(size[1], 10),
      steps: steps
    })
  })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      btn.disabled = false;
      if (data.ok && data.images && data.images.length) {
        statusEl.hidden = true;
        var img = document.createElement('img');
        img.src = 'data:image/png;base64,' + data.images[0];
        resultEl.appendChild(img);
      } else {
        statusEl.className = 'gen-status error';
        statusEl.textContent = data.error || '알 수 없는 오류가 발생했습니다.';
      }
    })
    .catch(function (err) {
      btn.disabled = false;
      statusEl.className = 'gen-status error';
      statusEl.textContent = '요청 중 오류가 발생했습니다: ' + err;
    });
});

document.getElementById('vid-submit').addEventListener('click', function () {
  var btn = this;
  var statusEl = document.getElementById('vid-status');
  var resultEl = document.getElementById('vid-result');
  var prompt = document.getElementById('vid-prompt').value.trim();
  var negative = document.getElementById('vid-negative').value.trim();
  var videoLength = parseInt(document.getElementById('vid-length').value, 10) || 16;
  var fps = parseInt(document.getElementById('vid-fps').value, 10) || 8;
  var steps = parseInt(document.getElementById('vid-steps').value, 10) || 20;

  if (!prompt) {
    statusEl.hidden = false;
    statusEl.className = 'gen-status error';
    statusEl.textContent = '프롬프트를 입력해 주세요.';
    return;
  }

  btn.disabled = true;
  statusEl.hidden = false;
  statusEl.className = 'gen-status loading';
  statusEl.textContent = '생성 중입니다… (프레임 수에 따라 1~수 분 걸릴 수 있습니다)';
  resultEl.innerHTML = '';

  fetch('/generate/video', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      prompt: prompt,
      negative_prompt: negative,
      video_length: videoLength,
      fps: fps,
      steps: steps
    })
  })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      btn.disabled = false;
      if (data.ok && data.video) {
        statusEl.hidden = true;
        var video = document.createElement('video');
        video.src = 'data:video/mp4;base64,' + data.video;
        video.controls = true;
        video.autoplay = true;
        video.loop = true;
        resultEl.appendChild(video);
      } else {
        statusEl.className = 'gen-status error';
        statusEl.textContent = data.error || '알 수 없는 오류가 발생했습니다.';
      }
    })
    .catch(function (err) {
      btn.disabled = false;
      statusEl.className = 'gen-status error';
      statusEl.textContent = '요청 중 오류가 발생했습니다: ' + err;
    });
});

document.getElementById('mus-submit').addEventListener('click', function () {
  var btn = this;
  var statusEl = document.getElementById('mus-status');
  var resultEl = document.getElementById('mus-result');
  var prompt = document.getElementById('mus-prompt').value.trim();
  var duration = parseInt(document.getElementById('mus-duration').value, 10) || 8;

  if (!prompt) {
    statusEl.hidden = false;
    statusEl.className = 'gen-status error';
    statusEl.textContent = '프롬프트를 입력해 주세요.';
    return;
  }

  btn.disabled = true;
  statusEl.hidden = false;
  statusEl.className = 'gen-status loading';
  statusEl.textContent = '생성 중입니다… (처음 실행 시 모델 로딩 때문에 더 걸릴 수 있습니다)';
  resultEl.innerHTML = '';

  fetch('/generate/music', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ prompt: prompt, duration: duration })
  })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      btn.disabled = false;
      if (data.ok && data.audio) {
        statusEl.hidden = true;
        var audio = document.createElement('audio');
        audio.src = 'data:audio/wav;base64,' + data.audio;
        audio.controls = true;
        audio.autoplay = true;
        resultEl.appendChild(audio);
      } else {
        statusEl.className = 'gen-status error';
        statusEl.textContent = data.error || '알 수 없는 오류가 발생했습니다.';
      }
    })
    .catch(function (err) {
      btn.disabled = false;
      statusEl.className = 'gen-status error';
      statusEl.textContent = '요청 중 오류가 발생했습니다: ' + err;
    });
});

document.getElementById('doc-submit').addEventListener('click', function () {
  var btn = this;
  var statusEl = document.getElementById('doc-status');
  var resultEl = document.getElementById('doc-result');
  var prompt = document.getElementById('doc-prompt').value.trim();

  if (!prompt) {
    statusEl.hidden = false;
    statusEl.className = 'gen-status error';
    statusEl.textContent = '프롬프트를 입력해 주세요.';
    return;
  }

  btn.disabled = true;
  statusEl.hidden = false;
  statusEl.className = 'gen-status loading';
  statusEl.textContent = '생성 중입니다…';
  resultEl.innerHTML = '';

  fetch('/generate/document', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ prompt: prompt })
  })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      btn.disabled = false;
      if (data.ok && data.text) {
        statusEl.hidden = true;
        var div = document.createElement('div');
        div.className = 'doc-text';
        div.textContent = data.text;
        resultEl.appendChild(div);
      } else {
        statusEl.className = 'gen-status error';
        statusEl.textContent = data.error || '알 수 없는 오류가 발생했습니다.';
      }
    })
    .catch(function (err) {
      btn.disabled = false;
      statusEl.className = 'gen-status error';
      statusEl.textContent = '요청 중 오류가 발생했습니다: ' + err;
    });
});

document.getElementById('code-submit').addEventListener('click', function () {
  var btn = this;
  var statusEl = document.getElementById('code-status');
  var resultEl = document.getElementById('code-result');
  var prompt = document.getElementById('code-prompt').value.trim();

  if (!prompt) {
    statusEl.hidden = false;
    statusEl.className = 'gen-status error';
    statusEl.textContent = '프롬프트를 입력해 주세요.';
    return;
  }

  btn.disabled = true;
  statusEl.hidden = false;
  statusEl.className = 'gen-status loading';
  statusEl.textContent = '생성 중입니다…';
  resultEl.innerHTML = '';

  fetch('/generate/code', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ prompt: prompt })
  })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      btn.disabled = false;
      if (data.ok && data.text) {
        statusEl.hidden = true;
        var pre = document.createElement('pre');
        pre.className = 'code-text';
        pre.textContent = data.text;
        resultEl.appendChild(pre);
      } else {
        statusEl.className = 'gen-status error';
        statusEl.textContent = data.error || '알 수 없는 오류가 발생했습니다.';
      }
    })
    .catch(function (err) {
      btn.disabled = false;
      statusEl.className = 'gen-status error';
      statusEl.textContent = '요청 중 오류가 발생했습니다: ' + err;
    });
});

document.getElementById('voice-submit').addEventListener('click', function () {
  var btn = this;
  var statusEl = document.getElementById('voice-status');
  var resultEl = document.getElementById('voice-result');
  var text = document.getElementById('voice-text').value.trim();
  var language = document.getElementById('voice-lang').value;
  var fileInput = document.getElementById('voice-ref');
  var file = fileInput.files[0];

  if (!text) {
    statusEl.hidden = false;
    statusEl.className = 'gen-status error';
    statusEl.textContent = '텍스트를 입력해 주세요.';
    return;
  }

  function submit(speakerWavB64) {
    btn.disabled = true;
    statusEl.hidden = false;
    statusEl.className = 'gen-status loading';
    statusEl.textContent = '생성 중입니다… (처음 실행 시 모델 로딩 때문에 더 걸릴 수 있습니다)';
    resultEl.innerHTML = '';

    var body = { text: text, language: language };
    if (speakerWavB64) { body.speaker_wav = speakerWavB64; }

    fetch('/generate/voice', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        btn.disabled = false;
        if (data.ok && data.audio) {
          statusEl.hidden = true;
          var audio = document.createElement('audio');
          audio.src = 'data:audio/wav;base64,' + data.audio;
          audio.controls = true;
          audio.autoplay = true;
          resultEl.appendChild(audio);
        } else {
          statusEl.className = 'gen-status error';
          statusEl.textContent = data.error || '알 수 없는 오류가 발생했습니다.';
        }
      })
      .catch(function (err) {
        btn.disabled = false;
        statusEl.className = 'gen-status error';
        statusEl.textContent = '요청 중 오류가 발생했습니다: ' + err;
      });
  }

  if (file) {
    var reader = new FileReader();
    reader.onload = function () {
      var base64 = reader.result.split(',')[1];
      submit(base64);
    };
    reader.readAsDataURL(file);
  } else {
    submit(null);
  }
});

function refreshStatus() {
  var listEl = document.getElementById('status-list');
  listEl.innerHTML = '확인 중…';
  fetch('/status')
    .then(function (res) { return res.json(); })
    .then(function (data) {
      listEl.innerHTML = '';
      (data.services || []).forEach(function (svc) {
        var row = document.createElement('div');
        row.className = 'status-row';
        var dot = document.createElement('span');
        dot.className = 'status-dot ' + (svc.ok ? 'up' : 'down');
        var label = document.createElement('span');
        label.className = 'status-label';
        label.textContent = svc.name;
        var text = document.createElement('span');
        text.className = 'status-text ' + (svc.ok ? 'up' : 'down');
        text.textContent = svc.ok ? '켜짐' : '꺼짐';
        row.appendChild(dot);
        row.appendChild(label);
        row.appendChild(text);
        listEl.appendChild(row);
      });
    })
    .catch(function (err) {
      listEl.textContent = '상태 확인 실패: ' + err;
    });
}
document.getElementById('status-refresh').addEventListener('click', refreshStatus);
refreshStatus();
</script>
</body>
</html>
    <?php
    return (string)ob_get_clean();
}
