<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>{{$title}}</title>
  <meta name="description" content="{{$description}}" />
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <script>
    window.settings = {
      title: '{{$title}}',
      version: '{{$version}}',
      description: '{{$description}}',
      logo: '{{$logo}}',
      assets_path: '/theme/{{$theme}}/assets',
      i18n: [
        'zh-CN',
        'en-US',
        'ja-JP',
        'vi-VN',
        'ko-KR',
        'zh-TW',
        'fa-IR'
      ],
      theme_color: '{{ $theme_config["theme_color"] ?? "default" }}',
      background_url: '{{ $theme_config["background_url"] ?? "" }}'
    };
  </script>
  @php
    $manifestPath = public_path('theme/' . $theme . '/assets/.vite/manifest.json');
    $manifest = [];
    if (file_exists($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true);
    }
    $jsFile = $manifest['index.html']['file'] ?? 'assets/app.js';
    $cssFile = $manifest['style.css']['file'] ?? 'assets/style.css';
  @endphp
  <script type="module" crossorigin src="/theme/{{$theme}}/{{ $jsFile }}"></script>
  <link rel="stylesheet" crossorigin href="/theme/{{$theme}}/{{ $cssFile }}" />
</head>
<body>
  <div id="app"></div>
  {!! $theme_config['custom_html'] ?? '' !!}
</body>
</html>
