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
</script>
</body>
</html>
    <?php
    return (string)ob_get_clean();
}
