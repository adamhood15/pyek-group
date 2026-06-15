<?php
$pg_bg   = esc_url( wp_get_attachment_url( 1271 ) );
$pg_logo = esc_url( wp_get_attachment_url( 964 ) );
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@200;300;400;600;700;800&family=Cormorant+Garamond:ital,wght@1,300&display=swap" rel="stylesheet">

<style>

/* ── Scoped reset ────────────────────────────────────────────── */
.pyek-page *,
.pyek-page *::before,
.pyek-page *::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* ── Design tokens ───────────────────────────────────────────── */
.pyek-page {
    --pg-navy:      #0A1B35;
    --pg-blue:      #1B5BAE;
    --pg-blue-lt:   #2E7FD4;
    --pg-cyan:      #4AC8D4;
    --pg-white:     #ffffff;
    --pg-muted:     rgba(168, 196, 224, 0.8);
}

/* ── Page wrapper ────────────────────────────────────────────── */
.pyek-page {
    background-color: var(--pg-navy);
    background-image: url('<?php echo $pg_bg; ?>');
    background-size: cover;
    background-position: center;
    background-attachment: fixed; /* note: ignored on iOS Safari — falls back gracefully */
    color: var(--pg-white);
    font-family: 'Montserrat', sans-serif;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 32px;
    position: relative;
    overflow-x: hidden;
}

/* Radial depth glow */
.pyek-page::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 80% 60% at 50% 42%, rgba(30, 70, 130, 0.38) 0%, transparent 70%);
    pointer-events: none;
    z-index: 0;
}

/* ── Content wrapper ─────────────────────────────────────────── */
.pyek-page .pg-wrapper {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 1280px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* ── Header ──────────────────────────────────────────────────── */
.pyek-page .pg-header {
    text-align: center;
    margin-bottom: 56px;
    animation: pg-fadeDown 0.9s ease both;
}

.pyek-page .pg-logo {
    width: 200px;
    max-width: 55vw;
    display: block;
    margin: 0 auto 26px;
    height: auto;
}

.pyek-page .pg-divider {
    display: flex;
    align-items: center;
    gap: 14px;
    justify-content: center;
    margin-bottom: 16px;
}

.pyek-page .pg-divider-line {
    width: 52px;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(74, 200, 212, 0.5));
}

.pyek-page .pg-divider-line:last-child {
    background: linear-gradient(270deg, transparent, rgba(74, 200, 212, 0.5));
}

.pyek-page .pg-divider-diamond {
    width: 5px;
    height: 5px;
    background: var(--pg-cyan);
    transform: rotate(45deg);
    opacity: 0.65;
}

.pyek-page .pg-tagline {
    font-family: 'Cormorant Garamond', serif;
    font-style: italic;
    font-weight: 300;
    font-size: 15px;
    letter-spacing: 4px;
    color: var(--pg-white);
}

/* ── Cards grid ──────────────────────────────────────────────── */
.pyek-page .pg-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    width: 100%;
    list-style: none;
}

.pyek-page .pg-card {
    position: relative;
    background: rgba(255, 255, 255, 0.035);
    border: 1px solid rgba(46, 127, 212, 0.14);
    border-top: 2px solid rgba(46, 127, 212, 0.42);
    border-radius: 2px;
    padding: 32px 24px 28px;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    transition:
        transform     0.35s ease,
        box-shadow    0.35s ease,
        background    0.35s ease,
        border-color  0.35s ease;
    animation: pg-fadeUp 0.7s ease both;
    overflow: hidden;
}

/* Shine overlay */
.pyek-page .pg-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(74, 200, 212, 0.05) 0%, transparent 55%);
    opacity: 0;
    transition: opacity 0.35s ease;
}

.pyek-page .pg-card:hover,
.pyek-page .pg-card:focus-visible {
    transform: translateY(-6px);
    background: rgba(255, 255, 255, 0.055);
    border-color: rgba(46, 127, 212, 0.28);
    border-top-color: var(--pg-cyan);
    box-shadow:
        0 22px 60px rgba(0, 0, 0, 0.45),
        0 0 0 1px rgba(74, 200, 212, 0.07);
}

.pyek-page .pg-card:focus-visible {
    outline: 2px solid var(--pg-cyan);
    outline-offset: 3px;
}

.pyek-page .pg-card:hover::before,
.pyek-page .pg-card:focus-visible::before {
    opacity: 1;
}

/* Staggered entrance */
.pyek-page .pg-card:nth-child(1) { animation-delay: 0.15s; }
.pyek-page .pg-card:nth-child(2) { animation-delay: 0.25s; }
.pyek-page .pg-card:nth-child(3) { animation-delay: 0.35s; }
.pyek-page .pg-card:nth-child(4) { animation-delay: 0.45s; }

.pyek-page .pg-eyebrow {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 4px;
    color: rgba(74, 200, 212, 0.5);
    margin-bottom: 6px;
}

.pyek-page .pg-card-name {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0.5px;
    color: var(--pg-white);
    margin-bottom: 14px;
    line-height: 1.1;
}

.pyek-page .pg-card-desc {
    font-size: 12.5px;
    font-weight: 300;
    color: var(--pg-muted);
    line-height: 1.72;
    flex-grow: 1;
    margin-bottom: 22px;
}

.pyek-page .pg-card-cta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 9.5px;
    font-weight: 600;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--pg-cyan);
    opacity: 0.7;
    transition: opacity 0.2s ease, gap 0.25s ease;
}

.pyek-page .pg-card:hover .pg-card-cta,
.pyek-page .pg-card:focus-visible .pg-card-cta {
    opacity: 1;
    gap: 10px;
}

.pyek-page .pg-cta-arrow {
    display: inline-block; /* required for transform */
    font-size: 13px;
    line-height: 1;
    transition: transform 0.25s ease;
}

.pyek-page .pg-card:hover .pg-cta-arrow,
.pyek-page .pg-card:focus-visible .pg-cta-arrow {
    transform: translateX(3px);
}

/* ── Footer ──────────────────────────────────────────────────── */
.pyek-page .pg-footer {
    margin-top: 48px;
    font-size: 11px;
    font-weight: 400;
    letter-spacing: 2px;
    color: rgba(168, 196, 224, 0.2);
    text-align: center;
    animation: pg-fadeUp 0.7s ease 0.6s both;
}

/* ── Animations ──────────────────────────────────────────────── */
@keyframes pg-fadeDown {
    from { opacity: 0; transform: translateY(-14px); }
    to   { opacity: 1; transform: translateY(0); }
}

@keyframes pg-fadeUp {
    from { opacity: 0; transform: translateY(22px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 900px) {
    .pyek-page .pg-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 500px) {
    .pyek-page              { padding: 48px 16px; }
    .pyek-page .pg-header   { margin-bottom: 40px; }
    .pyek-page .pg-logo     { width: 160px; }
    .pyek-page .pg-grid     { grid-template-columns: 1fr; gap: 14px; }
    .pyek-page .pg-card     { padding: 28px 22px 24px; }
    .pyek-page .pg-card-name { font-size: 20px; }
}

</style>

<div class="pyek-page">
    <div class="pg-wrapper">

        <header class="pg-header">
            <img
                class="pg-logo"
                src="<?php echo $pg_logo; ?>"
                alt="Pyek Group"
                width="200"
                height="auto"
            >
            <div class="pg-divider" aria-hidden="true">
                <span class="pg-divider-line"></span>
                <span class="pg-divider-diamond"></span>
                <span class="pg-divider-line"></span>
            </div>
            <p class="pg-tagline">Finance.&nbsp; Operations.&nbsp; Intelligence.&nbsp; Growth.</p>
        </header>

        <nav class="pg-grid" aria-label="Pyek Group divisions">

            <a class="pg-card"
               href="<?php echo esc_url( 'https://pyekfinancial.com' ); ?>"
               aria-label="Pyek Financial — fractional CFO and financial advisory services">
                <div class="pg-eyebrow" aria-hidden="true">PYEK</div>
                <div class="pg-card-name">Financial</div>
                <div class="pg-card-desc">Fractional CFO and financial advisory services for growing businesses.</div>
                <div class="pg-card-cta" aria-hidden="true">
                    pyekfinancial.com
                    <span class="pg-cta-arrow">→</span>
                </div>
            </a>

            <a class="pg-card"
               href="<?php echo esc_url( 'https://pyekmanagement.com' ); ?>"
               aria-label="Pyek Management — operational management and consulting">
                <div class="pg-eyebrow" aria-hidden="true">PYEK</div>
                <div class="pg-card-name">Management</div>
                <div class="pg-card-desc">Operational management and consulting for waterpark and hospitality assets.</div>
                <div class="pg-card-cta" aria-hidden="true">
                    pyekmanagement.com
                    <span class="pg-cta-arrow">→</span>
                </div>
            </a>

            <a class="pg-card"
               href="<?php echo esc_url( 'https://pyekai.com' ); ?>"
               aria-label="Pyek AI — custom AI agent development">
                <div class="pg-eyebrow" aria-hidden="true">PYEK</div>
                <div class="pg-card-name">AI</div>
                <div class="pg-card-desc">Custom AI agent development for businesses ready to put intelligence to work.</div>
                <div class="pg-card-cta" aria-hidden="true">
                    pyekai.com
                    <span class="pg-cta-arrow">→</span>
                </div>
            </a>

            <a class="pg-card"
               href="<?php echo esc_url( 'https://pyekcapital.com' ); ?>"
               aria-label="Pyek Capital — independent sponsor private equity">
                <div class="pg-eyebrow" aria-hidden="true">PYEK</div>
                <div class="pg-card-name">Capital</div>
                <div class="pg-card-desc">Independent sponsor private equity focused on lower middle market acquisitions.</div>
                <div class="pg-card-cta" aria-hidden="true">
                    pyekcapital.com
                    <span class="pg-cta-arrow">→</span>
                </div>
            </a>

        </nav>

        <footer class="pg-footer">
            <p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> Pyek Group</p>
        </footer>

    </div>
</div>
