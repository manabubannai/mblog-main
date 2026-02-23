<?php
if (!headers_sent()) {
	header('Cache-Control: public, max-age=300, stale-while-revalidate=86400');
	header('Vary: Accept-Encoding');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?? 'manablog' ?></title>
<?php if (!empty($page_description)): ?>
<meta name="description" content="<?= htmlspecialchars($page_description) ?>">
<meta property="og:description" content="<?= htmlspecialchars($page_description) ?>">
<?php endif; ?>
<?php if (!empty($page_title)): ?>
<meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
<meta property="og:type" content="article">
<?php endif; ?>
<meta property="og:site_name" content="manablog">
<link rel="shortcut icon" href="https://manablog.org/wp-content/themes/manabu/images/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap"></noscript>
<link rel="preconnect" href="https://use.typekit.net" crossorigin>
<link rel="stylesheet" href="https://use.typekit.net/jkb4xph.css" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://use.typekit.net/jkb4xph.css"></noscript>

<style>
/* Base */
body {
  max-width: 680px;
  margin: auto;
  padding: 20px;
  font-family: "adelle", Noto, "Hiragino Sans", serif;
  font-size: 16px;
  color: #333;
  -webkit-font-smoothing: antialiased;
  text-rendering: optimizeLegibility;
}
a { color: #2121d3d9; }
img { max-width: 85%; margin: auto; display: block; }
hr { background: rgba(0,0,0,0.15); height: 1px; border: 0; margin: 25px 0; }

/* Logo */
img.logo { max-width: 130px; margin: 12px 0 25px; display: block; }

/* English Article */
h1.title { font-size: 27px; font-weight: 600; margin: 25px 0 27px; color: #454545; }
h2, h3 { font-weight: 600; }
p { font-size: 17px; line-height: 1.75; }
ul {
  line-height: 1.6; font-size: 16.5px;
  background: rgba(250,250,250,0.48);
  outline: 1px solid rgba(228,228,228,0.87);
  list-style: disc;
  padding: 20px 20px 21px 30px;
}
li { display: list-item; }
ul.long_list li:not(:last-child) { margin-bottom: 10px; }
pre {
  white-space: pre-wrap; word-break: break-all; overflow-wrap: break-word;
  font-family: 'SFMono-Regular', Consolas, Menlo, monospace;
  font-size: 15.5px; line-height: 1.75; padding: 20px;
  background: #f7f7f7; outline: 1px solid rgba(210,210,210,0.8);
}
pre a { color: #2121d3d9; text-decoration: underline; text-underline-offset: 3px; }
blockquote { margin: 20px 0 20px 2px; padding: 0 0 0 15px; border-left: 2px solid rgba(75,75,75,0.8); }
blockquote p { font-size: 17px; font-style: italic; color: #6a6a6a; }
blockquote p::before { content: "\00BB  "; }

/* Top Page */
ul.toppage { font-size: 16.5px; list-style: none; padding: 0; background: #fff; outline: none; }
ul.toppage li { display: flex; padding: 3px 0; }
time { flex: 0 0 105px; }
a { flex: 1; }

/* JP Article */
.jp-article {
  font-family: Noto, "Hiragino Sans", "Noto Sans JP", Helvetica, Arial, sans-serif;
  line-height: 2;
}
.jp-article h1.title { font-size: 26px; font-weight: 600; margin: 0 0 40px; line-height: 1.7; }
.jp-article h2 { font-size: 24px; font-weight: 600; margin: 40px 0 15px; line-height: 1.7; }
.jp-article h2::before { content: "\25A0  "; font-family: system-ui; }
.jp-article h3 { font-size: 21px; font-weight: 600; margin: 40px 0 15px; line-height: 1.7; }
.jp-article h3::before { content: "\25A1  "; font-family: system-ui; }
.jp-article h4 { font-size: 18px; font-weight: 600; margin: 40px 0 -5px; line-height: 1.7; }
.jp-article h4::before { content: "\2713  "; font-family: system-ui; }
.jp-article p { font-size: 16.5px; line-height: 1.7; margin: 20px 0 35px; }
.jp-article ul, .jp-article ol {
  font-size: 16.5px; line-height: 2;
  padding: 20px 10px 20px 30px; margin: 20px 0 35px;
}
.jp-article ul.long_list li { line-height: 1.7; margin-bottom: 20px; }
.jp-article blockquote { padding-left: 20px; border-left: 2.5px solid rgba(86,86,86,0.85); font-style: italic; margin: 20px 0 35px; }
.jp-article blockquote p { margin: 0; font-size: 16.5px; }
.jp-article blockquote p::before { content: "\00BB  "; }
.jp-article a { color: #337ab7; text-decoration: underline; }
.jp-article img { max-width: 75%; margin: auto auto 25px; }
.jp-article time { font-size: 16.5px; margin-bottom: 20px; display: block; }
.jp-article hr { background: rgba(0,0,0,0.1); margin: 30px 0; }
.jp-article table { width: 100%; border-collapse: collapse; margin: 28px 0; font-size: 15px; }
.jp-article th { background: #f5f5f5; font-weight: 600; text-align: left; padding: 10px 14px; border: 1px solid #e0e0e0; }
.jp-article td { padding: 10px 14px; border: 1px solid #e0e0e0; }
.jp-article pre { font-family: Menlo, Monaco, Consolas, monospace; font-size: 13.5px; background: #f7f7f7; border: 1px solid #eee; border-radius: 4px; }
.jp-article .faq-q { margin-bottom: 5px; }
.jp-article .faq-a { margin-top: 0; }
.pc-br { display: inline; }
.sp-br { display: none; }

/* Health Log */
.jp-font {
  font-family: Noto, "Hiragino Sans", "Hiragino Kaku Gothic ProN", "Yu Gothic", Meiryo, sans-serif;
  font-size: 14px; line-height: 28px; -webkit-font-smoothing: antialiased;
}
.health-section {
  font-family: Noto, "Hiragino Sans", "Hiragino Kaku Gothic ProN", "Yu Gothic", Meiryo, sans-serif;
  font-size: 14px; line-height: 1.8; color: #333; -webkit-font-smoothing: antialiased;
}
.health-section pre {
  font-family: Menlo, Monaco, Consolas, monospace; font-size: 13.5px; line-height: 1.6;
  padding: 15px !important; border: 1px solid rgba(228,228,228,0.87) !important;
  border-radius: 0; color: #333; white-space: pre-wrap;
}
.health-section pre a { color: #1a73e8 !important; text-decoration: underline; }
.health-section pre a:hover { color: #174ea6 !important; }
.hs-box { border-radius: 8px; padding: 20px; margin: 20px 0; }
.hs-gray { background: #f8f8f8; border: 1px solid #e0e0e0; }
.hs-blue { background: #f0f7ff; border: 1px solid #c8ddf5; }
.hs-notice { border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; background: #f0f7ff; border: 1px solid #c8ddf5; font-size: 13px; font-style: italic; color: #4a6a8a; line-height: 1.6; }
.hs-box p, .hs-box span, .hs-box li { font-size: 14px; line-height: 1.7; margin-top: 0; margin-bottom: 10px; }
.hs-title { font-weight: bold; font-size: 16px; margin-bottom: 12px; }
.hs-desc { color: #555; margin-bottom: 12px; }
.hs-subdesc { font-size: 13px; color: #666; margin-bottom: 12px; }
.hs-prompt { font-size: 12px; line-height: 1.5; max-height: 200px; overflow-y: auto; background: #fff; cursor: text; }
.hs-result { margin-top: 16px; padding: 16px; background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 14px; line-height: 1.8; white-space: pre-wrap; word-wrap: break-word; }
.hs-textarea { width: 100%; box-sizing: border-box; padding: 12px; font-size: 14px; font-family: Noto, 'Hiragino Sans', 'Yu Gothic', Meiryo, sans-serif; border: 1px solid #ccc; border-radius: 6px; resize: vertical; line-height: 1.6; background: #fff; color: #333; }
.hs-btn { padding: 10px 24px; background: #111; color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; }
.hs-copy-btn { display: inline-block; margin-top: 10px; padding: 8px 20px; background: #111; color: #fff; border-radius: 6px; text-decoration: none; cursor: pointer; font-size: 14px; }
.body-comparison { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 20px 0 24px; }
.body-comparison .bc-card { border: 1px solid rgba(228,228,228,0.87); overflow: hidden; background: rgba(250,250,250,0.48); }
.body-comparison .bc-photo { width: 100%; aspect-ratio: 3/4; overflow: hidden; }
.body-comparison .bc-photo img { width: 100%; height: 100%; object-fit: cover; object-position: top; display: block; }
.body-comparison .bc-body { padding: 10px 12px 12px; }
.body-comparison .bc-label { font-size: 11px; font-weight: 700 !important; color: #666; text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 4px; }
.body-comparison .bc-goal .bc-label { color: #2d4a3e; }
.body-comparison .bc-date { font-size: 13px; font-weight: 600; color: #333; margin-bottom: 8px; }
.body-comparison .bc-goal .bc-date { color: #2d4a3e; }
.body-comparison .bc-stats { list-style: none; padding: 0; margin: 0; outline: none; background: none; font-size: 11.5px; line-height: 1; color: #666; }
.body-comparison .bc-stats li { display: flex; justify-content: space-between; border-bottom: 1px solid rgba(0,0,0,0.04); padding: 4px 0; }
.body-comparison .bc-stats li:last-child { border-bottom: none; }
.body-comparison .bc-stats .bc-val { font-weight: 600; color: #333; }

/* Mobile */
@media (max-width: 480px) {
  img.logo { max-width: 120px; }
  ul.toppage li { padding: 5px 0; }
  .pc-br { display: none; }
  .sp-br { display: block; margin-bottom: 15px; }
  .jp-article h1.title { font-size: 20px; margin-bottom: 25px; }
  .jp-article h2 { font-size: 19px; }
  .jp-article h3 { font-size: 17px; }
  .jp-article h4 { font-size: 16px; }
  .jp-article p { font-size: 15px; line-height: 1.6; margin: 12px 0 25px; }
  .jp-article ul, .jp-article ol { font-size: 14.7px; line-height: 1.7; margin: 12px 0 25px; }
  .jp-article blockquote { margin: 15px 0 22px; }
  .jp-article blockquote p { font-size: 15px; }
  .jp-article time { font-size: 12px; margin-bottom: 10px; }
  .jp-article img { max-width: 95%; }
  .jp-article table { font-size: 13px; }
  .jp-article th, .jp-article td { padding: 8px 10px; }
  .jp-article pre { padding: 15px !important; word-break: break-all; }
}
@media (min-width: 768px) {
  img { max-width: 60%; }
}
</style>
</head>

<body>
