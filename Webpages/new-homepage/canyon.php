<?php
// Video section group
$video_section = get_field("video_section") ?: [];

// Video group
$hero_video = $video_section["hero_video"] ?? [];

$video_mp4  = $hero_video["mp4"]  ?? "/wp-content/uploads/2026/05/CBC-Web.mp4";
$video_webm = $hero_video["webm"] ?? "/wp-content/uploads/2026/05/CBC-Web.webm";

$poster_desktop_field = $hero_video["poster_desktop"] ?? [];

$poster_desktop = $poster_desktop_field["url"]    ?? "/wp-content/uploads/2026/05/canyon-poster-desktop.jpg";
$poster_width   = $poster_desktop_field["width"]  ?? 1920;
$poster_height  = $poster_desktop_field["height"] ?? 1080;

// Other fields
$logo = $video_section["hero_logo"] ?? "/wp-content/uploads/2023/01/cowabunga-vegas-logo.png";

// Headings
$heading_top    = $video_section["hero_top_heading"]       ?? "VEGAS\u{2019} ULTIMATE";
$heading_middle = $video_section["hero_subheading_styled"] ?? "Waterparks";
$heading_bottom = $video_section["hero_heading_bottom"]    ?? "EXPERIENCE";

// Button group
$hero_button = $video_section["hero_button"] ?? [];

$button_text = $hero_button["text"] ?? "Get Tickets";
$button_url  = $hero_button["url"]  ?? "https://cowabungavegas.com/canyon/buy-tickets/";
?>

<link rel="preload" as="image" href="<?php echo esc_url($poster_desktop); ?>">

<section class="hero" style="position:relative;height:100vh;overflow:hidden;">
  <div class="hero__media" style="position:absolute;inset:0;width:100%;height:100%;">

    <img
      class="hero__poster"
      style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;display:block;z-index:0;"
      src="<?php echo esc_url($poster_desktop); ?>"
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
      alt="Cowabunga Vegas Waterparks Logo"
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

    <a href="<?php echo esc_url($button_url); ?>" class="nz-button-teal">
      <?php echo esc_html($button_text); ?>
    </a>
  </div>
</section>
