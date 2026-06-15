<?php
// Video section group
$video_section = get_field("video_section") ?: [];

// Video group
$hero_video = $video_section["hero_video"] ?? [];

$video_mp4  = $hero_video["mp4"]  ?? "/wp-content/uploads/2024/05/Typhoon_Texas-Houston_hero.mp4";
$video_webm = $hero_video["webm"] ?? "/wp-content/uploads/2026/04/Typhoon_Texas-Houston_hero.webm";

$poster_field = $hero_video["poster_desktop"] ?? [];

$poster        = $poster_field["url"]    ?? "/wp-content/uploads/2026/04/houston-video-poster.jpg";
$poster_width  = $poster_field["width"]  ?? 1920;
$poster_height = $poster_field["height"] ?? 1080;

// Other fields
$logo = $video_section["hero_logo"] ?? "/wp-content/uploads/2026/02/White_Verticle_TTLogo.png";

// Headings
$heading_top    = $video_section["hero_top_heading"]       ?? "Houston\u{2019}s Ultimate";
$heading_middle = $video_section["hero_subheading_styled"] ?? "Waterpark";
$heading_bottom = $video_section["hero_bottom_heading"]    ?? "Experience";

// Button group
$hero_button = $video_section["hero_button"] ?? [];

$button_text = $hero_button["text"] ?? "Get Tickets";
$button_url  = $hero_button["url"]  ?? "https://typhoontexas.com/houston/buy-tickets/";
?>

<link rel="preload" as="image" href="<?php echo esc_url($poster); ?>">

<section class="hero" style="position:relative;height:100vh;overflow:hidden;">
  <div class="hero__media" style="position:absolute;inset:0;width:100%;height:100%;">

    <img
      class="hero__poster"
      style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;display:block;z-index:0;"
      src="<?php echo esc_url($poster); ?>"
      alt=""
      width="<?php echo esc_attr($poster_width); ?>"
      height="<?php echo esc_attr($poster_height); ?>"
      loading="eager"
      fetchpriority="high"
      decoding="async"
    >

    <video
      class="hero__video"
      style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;"
      autoplay
      muted
      loop
      playsinline
      preload="metadata"
    >
      <?php if ($video_webm) : ?>
      <source src="<?php echo esc_url($video_webm); ?>" type="video/webm">
      <?php endif; ?>
      <source src="<?php echo esc_url($video_mp4); ?>" type="video/mp4">
    </video>

  </div>

  <div class="hero__overlay">
    <img
      src="<?php echo esc_url($logo); ?>"
      alt="Typhoon Texas Logo"
      class="hero__logo"
      loading="eager"
      decoding="async"
    >

    <h1 class="hero__heading">
      <?php echo esc_html($heading_top); ?><br>
      <span class="text-stroke guttery hero__heading--styled">
        <?php echo esc_html($heading_middle); ?>
      </span><br>
      <?php echo esc_html($heading_bottom); ?>
    </h1>

    <a href="<?php echo esc_url($button_url); ?>" class="nz-button-aqua">
      <?php echo esc_html($button_text); ?>
    </a>
  </div>
</section>
