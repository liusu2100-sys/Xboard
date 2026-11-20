<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>{{$title}}</title>
  <meta name="description" content="{{$description}}" />
  <link rel="icon" href="data:," />
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
      background_url: '{{ $theme_config["background_url"] ?? "" }}',
      home_slogan: @json($theme_config['home_slogan'] ?? '无限制畅享全球网络加速'),
      promo_enable: '{{ $theme_config["promo_enable"] ?? "1" }}',
      promo_title: @json($theme_config['promo_title'] ?? 'EveryOne 收下这份礼物吧！'),
      promo_subtitle: @json($theme_config['promo_subtitle'] ?? '本次优惠金额'),
      promo_amount: @json($theme_config['promo_amount'] ?? '0.00'),
      promo_coupon_code: @json($theme_config['promo_coupon_code'] ?? ''),
      promo_ends_at: @json($theme_config['promo_ends_at'] ?? '')
    };
  </script>
  @php
    $themePath = public_path('theme/' . $theme);
    $manifestPath = $themePath . '/assets/.vite/manifest.json';
    $manifest = [];
    if (file_exists($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
    }

    $stableJs = 'assets/app.js';
    $stableCss = 'assets/style.css';
    $manifestJs = $manifest['index.html']['file'] ?? null;
    $manifestCss = $manifest['style.css']['file'] ?? null;

    // Prefer stable filenames to avoid blank pages when .vite is not uploaded
    // or CDN/browser cache still points at removed hashed assets.
    $jsFile = file_exists($themePath . '/' . $stableJs)
        ? $stableJs
        : (($manifestJs && file_exists($themePath . '/' . $manifestJs)) ? $manifestJs : 'assets/app.js');
    $cssFile = file_exists($themePath . '/' . $stableCss)
        ? $stableCss
        : (($manifestCss && file_exists($themePath . '/' . $manifestCss)) ? $manifestCss : 'assets/style.css');
    $assetVersion = file_exists($themePath . '/' . $jsFile)
        ? filemtime($themePath . '/' . $jsFile)
        : time();
  @endphp
  <script type="module" crossorigin src="/theme/{{$theme}}/{{ $jsFile }}"></script>
  <link rel="stylesheet" crossorigin href="/theme/{{$theme}}/{{ $cssFile }}?v={{ $assetVersion }}" />
</head>
<body>
  <div id="app"></div>
  {!! $theme_config['custom_html'] ?? '' !!}
</body>
</html>
