<?php
/**
 * 화면 녹화 · 녹음 페이지.
 * 창/모니터를 골라 바로 시작·중지하고(rec.ps1 을 그대로 호출), 결과물을 내려받는다.
 */

require __DIR__ . '/lib.php';

$isLocal = recIsLocalRequest();
$message = isset($_GET['msg']) ? $_GET['msg'] : '';
$isError = isset($_GET['err']) && $_GET['err'] === '1';

$status = recPowerShell("& powershell -ExecutionPolicy Bypass -NoProfile -File '" . REC_ROOT . "\\rec.ps1' -Action status");
$recording = (strpos($status, '녹화 중 —') !== false);

$windows = [];
$raw = recPowerShell("& powershell -ExecutionPolicy Bypass -NoProfile -File '" . REC_ROOT . "\\list-windows.ps1'");
foreach (preg_split('/\r?\n/', $raw) as $line) {
    if (preg_match('/rect=(-?\d+),(-?\d+) (\d+)x(\d+) title=(.+)$/u', $line, $m)) {
        $title = trim($m[5]);
        if ($title === '' || (int)$m[3] < 400) {
            continue;
        }
        $windows[$title] = sprintf('%s  (%sx%s)', $title, $m[3], $m[4]);
    }
}

$files = [];
if (is_dir(REC_OUT)) {
    foreach (scandir(REC_OUT) as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        $path = REC_OUT . '\\' . $f;
        if (is_file($path)) {
            $files[] = ['name' => $f, 'size' => filesize($path), 'time' => filemtime($path)];
        }
    }
    usort($files, function ($a, $b) { return $b['time'] - $a['time']; });
    $files = array_slice($files, 0, 12);
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>화면 녹화 · swbins3</title>
<style>
  * { box-sizing: border-box; }
  :root {
    --bg: #eef1f5; --card: #fff; --card-border: #d7dee6;
    --text: #1f2937; --muted: #5b6b7c; --faint: #8b98a6;
    --accent: #1d5ea8; --up: #1a7f37; --down: #b3261e;
    --topbar: #16324f; --topbar-line: #0e2338; --topbar-text: #eef3f8;
  }
  body { margin: 0; padding: 0; background: var(--bg); color: var(--text);
         font-family: "Pretendard", "Malgun Gothic", -apple-system, sans-serif; }
  header { background: var(--topbar); border-bottom: 3px solid var(--topbar-line); }
  .header-inner { max-width: 980px; margin: 0 auto; padding: 14px 24px; }
  header h1 { margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--topbar-text); }
  .wrap { max-width: 980px; margin: 0 auto; padding: 20px 24px 40px; }
  .card { background: var(--card); border: 1px solid var(--card-border); border-radius: 3px;
          padding: 16px 18px; margin-bottom: 12px; }
  .card h2 { font-size: .85rem; margin: 0 0 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; font-weight: 700; }
  .rec-on { color: var(--down); font-weight: 700; }
  .rec-off { color: var(--muted); }
  pre { background: #e4e9ef; border: 1px solid var(--card-border); border-radius: 3px;
        padding: 10px 12px; font-size: 12px; white-space: pre-wrap; margin: 0 0 12px; }
  label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; font-weight: 600; }
  select, input[type=text], input[type=number] {
    width: 100%; padding: 8px 10px; border: 1px solid var(--card-border); border-radius: 3px;
    font-size: 13px; font-family: inherit; background: #fff; color: var(--text); }
  .row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
  .row > div { flex: 1 1 150px; }
  button { padding: 9px 18px; border: 1px solid var(--card-border); border-radius: 3px; font-size: 13px;
           font-weight: 700; cursor: pointer; background: #f3f5f8; color: #475569; font-family: inherit; }
  .go { background: var(--accent); border-color: var(--accent); color: #fff; }
  .stop { background: var(--down); border-color: var(--down); color: #fff; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  td, th { padding: 7px 6px; border-bottom: 1px solid var(--card-border); text-align: left; }
  th { color: var(--muted); font-weight: 700; font-size: 12px; }
  .msg { padding: 10px 14px; border-radius: 3px; margin-bottom: 16px; font-size: 13px; border: 1px solid; }
  .ok { background: #eaf5ec; border-color: #b9dcc0; color: #185c2c; }
  .bad { background: #fbeae9; border-color: #eec2be; color: #8c2119; }
  .hint { font-size: 12px; color: var(--faint); margin-top: 8px; line-height: 1.6; }
  a { color: var(--accent); }
</style>
</head>
<body>
<header>
  <div class="header-inner">
    <h1>🎬 화면 녹화 · 녹음</h1>
  </div>
</header>
<div class="wrap">

<?php if ($message !== '') { ?>
  <div class="msg <?php echo $isError ? 'bad' : 'ok'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

  <div class="card">
    <h2>지금 상태 <span class="<?php echo $recording ? 'rec-on' : 'rec-off'; ?>">
      <?php echo $recording ? '● 녹화 중' : '○ 대기'; ?></span></h2>
    <pre><?php echo htmlspecialchars(trim($status), ENT_QUOTES, 'UTF-8'); ?></pre>
<?php if ($isLocal) { ?>
    <form method="post" action="/action" style="display:inline">
      <input type="hidden" name="do" value="stop">
      <button type="submit" class="stop" <?php echo $recording ? '' : 'disabled'; ?>>중지하고 저장</button>
    </form>
<?php } ?>
  </div>

<?php if ($isLocal) { ?>
  <div class="card">
    <h2>바로 녹화</h2>
    <form method="post" action="/action">
      <input type="hidden" name="do" value="start">
      <div class="row">
        <div style="flex:2 1 320px">
          <label>대상</label>
          <select name="target">
            <option value="screen">활성 창이 있는 화면을 따라감</option>
            <option value="monitor1">모니터 1 전체</option>
            <option value="monitor2">모니터 2 전체</option>
            <option value="monitor3">모니터 3 전체</option>
<?php foreach ($windows as $title => $label) { ?>
            <option value="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">창: <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
<?php } ?>
          </select>
        </div>
        <div>
          <label>형식</label>
          <select name="format">
            <option value="mp4">mp4 (가볍다)</option>
            <option value="gif">gif (노션에 바로 붙는다)</option>
          </select>
        </div>
        <div>
          <label>소리</label>
          <select name="audio" disabled>
            <option value="none">없음</option>
          </select>
        </div>
        <div>
          <label>fps</label>
          <input type="number" name="fps" value="10" min="1" max="30">
        </div>
        <div>
          <label>최대 초</label>
          <input type="number" name="seconds" value="300" min="5" max="3600">
        </div>
      </div>
      <button type="submit" class="go">녹화 시작</button>
      <div class="hint">
        창을 고르면 <b>그 창만</b> 직접 캡처해서, 다른 창이 위에 겹치거나 다른 모니터에 있어도 그대로 잡힙니다.<br>
        모니터·화면 추적은 <b>보이는 그대로</b> 찍히므로 다른 화면이 함께 담길 수 있습니다.<br>
        <b>소리는 이 화면에서 못 받습니다.</b> 웹이 띄운 프로세스로는 마이크가 잡히지 않아,
        녹음이 필요하면 콘솔에서 직접 실행하세요: <code>rec.ps1 -Action audio -Seconds 60</code>
      </div>
    </form>
  </div>
<?php } else { ?>
  <div class="card"><h2>조작은 이 PC에서만</h2>
    <div class="hint">녹화 시작·중지는 화면을 캡처하는 기능이라 이 PC에서 연 화면에서만 할 수 있습니다.</div>
  </div>
<?php } ?>

  <div class="card">
    <h2>결과물</h2>
<?php if (!$files) { ?>
    <div class="hint">아직 없습니다.</div>
<?php } else { ?>
    <table>
      <tr><th>파일</th><th style="width:90px">크기</th><th style="width:130px">시각</th></tr>
<?php foreach ($files as $f) { ?>
      <tr>
        <td><a href="/file?f=<?php echo urlencode($f['name']); ?>"><?php echo htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
        <td><?php echo recSize($f['size']); ?></td>
        <td><?php echo date('m-d H:i', $f['time']); ?></td>
      </tr>
<?php } ?>
    </table>
<?php } ?>
  </div>
</div>
</body>
</html>
