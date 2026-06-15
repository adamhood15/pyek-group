<?php
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// =========================
// ACF FIELDS
// =========================

// Pull the parent "Video Section" group first.
// ⚠️ Verify this slug matches the Field Name in ACF admin
// (edit the group field → "Field Name" row — not the label).
$vs = get_field('video_section') ?: [];

// hero_video sub-group
$hero_video  = $vs['hero_video'] ?? [];
$video_mp4   = $hero_video['mp4']    ?? '/wp-content/uploads/2026/04/TTA-Web.mp4';
$video_webm  = $hero_video['webm']   ?? '/wp-content/uploads/2026/04/TTA-Web.webm';
$poster      = $hero_video['poster'] ?? '/wp-content/uploads/2026/04/TTA-Web-Poster.jpg';

// Flat subfields of Video Section
$logo            = $vs['hero_logo']              ?? '/wp-content/uploads/2026/02/White_Verticle_TTLogo.png';
$heading_top     = $vs['hero_top_heading']       ?? 'Austin\'s Ultimate';
$heading_styled  = $vs['hero_subheading_styled'] ?? 'Waterpark';
$heading_bottom  = $vs['hero_bottom_heading']    ?? 'Experience';

// hero_button sub-group
$hero_button = $vs['hero_button'] ?? [];
$button_text = $hero_button['text'] ?? 'Get Tickets';
$button_url  = $hero_button['url']  ?? 'https://typhoontexas.com/austin/buy-tickets/';

// =========================
// BROWSER DETECTION
// =========================

$is_safari = (
    strpos($user_agent, 'Safari') !== false &&
    strpos($user_agent, 'Chrome') === false &&
    strpos($user_agent, 'Chromium') === false
);

$video_src  = $is_safari ? $video_mp4 : ($video_webm ?: $video_mp4);
$video_type = $is_safari
    ? 'video/mp4'
    : ($video_webm ? 'video/webm' : 'video/mp4');
?>

<?php
add_action('wp_head', function() use ($poster, $logo) {
    echo '<link rel="preload" as="image" fetchpriority="high" href="' . esc_url($poster) . '">' . "\n";
    if ($logo) {
        echo '<link rel="preload" as="image" fetchpriority="high" href="' . esc_url($logo) . '">' . "\n";
    }
}, 1);
?>

<section class="hero">

  <div class="hero__media">
    <video
      class="hero__video"
      autoplay
      muted
      loop
      playsinline
      preload="none"
      fetchpriority="low"
      poster="<?php echo esc_url($poster); ?>"
    >
      <source
        src="<?php echo esc_url($video_src); ?>"
        type="<?php echo esc_attr($video_type); ?>"
      >
    </video>
  </div>

  <div class="hero__overlay">

    <?php if ($logo) : ?>
      <img
        src="<?php echo esc_url($logo); ?>"
        alt="Typhoon Texas Logo"
        class="hero__logo"
        loading="eager"
        fetchpriority="high"
        decoding="sync"
      >
    <?php endif; ?>

    <h1 class="hero__heading">
      <?php echo esc_html($heading_top); ?><br>

      <span class="text-stroke guttery">
        <?php echo esc_html($heading_styled); ?><br>
      </span>
      <?php echo esc_html($heading_bottom); ?>
    </h1>

    <?php if ($button_text && $button_url) : ?>
      <a
        href="<?php echo esc_url($button_url); ?>"
        class="nz-button-aqua"
      >
        <?php echo esc_html($button_text); ?>
      </a>
    <?php endif; ?>

  </div>

</section>
