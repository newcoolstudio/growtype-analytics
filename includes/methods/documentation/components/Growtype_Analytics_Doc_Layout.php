<?php

/**
 * Reusable component: Documentation site HTML shell / layout
 *
 * Renders the full-page HTML wrapper (head, topbar, sidebar, mobile nav,
 * content area) and delegates inner content to the active page class.
 *
 * Usage:
 *   (new Growtype_Analytics_Doc_Layout($page))->render($active_page);
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/documentation/components
 */
class Growtype_Analytics_Doc_Layout
{
    /** Navigation items: slug → ['label', 'icon'] */
    private const NAV = [
        'metrics'  => ['label' => 'Metrics',  'icon' => '📊'],
        'strategy' => ['label' => 'Strategy', 'icon' => '🎯'],
    ];

    /** @var Growtype_Analytics_Doc_Renderer */
    private $renderer;

    public function __construct(Growtype_Analytics_Doc_Renderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function render(string $active_page): void
    {
        $nav = self::NAV;
        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Analytics Docs — <?php echo esc_html(get_bloginfo('name')); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-w: 240px;
            --accent:    #2563eb;
            --accent-lt: #dbeafe;
            --bg:        #f8fafc;
            --surface:   #ffffff;
            --border:    #e2e8f0;
            --text:      #1e293b;
            --muted:     #64748b;
            --radius:    10px;
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; }

        /* ── Top bar ── */
        #ga-topbar {
            height: 54px; background: var(--surface); border-bottom: 1px solid var(--border);
            display: flex; align-items: center; padding: 0 24px; gap: 16px;
            position: sticky; top: 0; z-index: 100;
        }
        #ga-topbar .logo { font-weight: 700; font-size: 15px; color: var(--accent); letter-spacing: -.3px; }
        #ga-topbar .sep  { color: var(--border); }
        #ga-topbar .site { font-size: 13px; color: var(--muted); }
        #ga-topbar .spacer { flex: 1; }
        #ga-topbar .admin-btn {
            font-size: 12px; font-weight: 500; color: var(--muted); text-decoration: none;
            padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px;
            transition: all .15s;
        }
        #ga-topbar .admin-btn:hover { color: var(--accent); border-color: var(--accent); }

        /* ── Layout ── */
        #ga-layout { display: flex; flex: 1; }

        /* ── Sidebar ── */
        #ga-sidebar {
            width: var(--sidebar-w); flex-shrink: 0;
            background: var(--surface); border-right: 1px solid var(--border);
            padding: 24px 12px; position: sticky; top: 54px; height: calc(100vh - 54px); overflow-y: auto;
        }
        .nav-group-label { font-size: 10px; font-weight: 700; letter-spacing: .08em; color: var(--muted); text-transform: uppercase; padding: 0 12px 8px; }
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 12px; border-radius: 7px; margin-bottom: 2px;
            font-size: 13.5px; font-weight: 500; color: var(--muted);
            text-decoration: none; transition: all .15s;
        }
        .nav-item:hover  { background: var(--bg); color: var(--text); }
        .nav-item.active { background: var(--accent-lt); color: var(--accent); }
        .nav-item .icon  { font-size: 16px; line-height: 1; }

        /* ── Content ── */
        #ga-content { flex: 1; padding: 40px 48px; max-width: 900px; }

        .doc-header { margin-bottom: 36px; }
        .doc-header h1 { font-size: 28px; font-weight: 700; letter-spacing: -.5px; margin-bottom: 8px; }
        .doc-header p  { font-size: 15px; color: var(--muted); line-height: 1.6; }

        .doc-section { margin-bottom: 40px; }
        .doc-section h2 {
            font-size: 17px; font-weight: 600; margin-bottom: 14px;
            padding-bottom: 10px; border-bottom: 1px solid var(--border);
        }
        .doc-section p, .doc-section li { font-size: 14.5px; color: #334155; line-height: 1.7; }
        .doc-section ul { padding-left: 20px; }
        .doc-section li { margin-bottom: 6px; }

        /* KPI cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; margin-top: 16px; }
        .kpi-card {
            background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 16px 18px;
        }
        .kpi-card .kpi-name { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--accent); margin-bottom: 6px; }
        .kpi-card .kpi-def  { font-size: 13px; color: var(--muted); line-height: 1.5; }

        /* Strategy steps */
        .strategy-steps { display: flex; flex-direction: column; gap: 20px; margin-top: 16px; }
        .step {
            display: flex; gap: 16px; align-items: flex-start;
            background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 18px 20px;
        }
        .step-num {
            width: 32px; height: 32px; flex-shrink: 0; border-radius: 50%;
            background: var(--accent); color: #fff; font-weight: 700; font-size: 14px;
            display: flex; align-items: center; justify-content: center;
        }
        .step-body h3 { font-size: 14.5px; font-weight: 600; margin-bottom: 5px; }
        .step-body p  { font-size: 13.5px; color: var(--muted); line-height: 1.6; }

        /* Callout */
        .callout {
            background: var(--accent-lt); border-left: 3px solid var(--accent);
            border-radius: 0 var(--radius) var(--radius) 0;
            padding: 14px 18px; margin-top: 16px;
            font-size: 13.5px; color: #1e3a5f; line-height: 1.6;
        }

        /* ── Mobile nav tab bar ── */
        #ga-mobile-nav {
            display: none;
            background: var(--surface); border-bottom: 1px solid var(--border);
            position: sticky; top: 54px; z-index: 90;
            overflow-x: auto; -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        #ga-mobile-nav::-webkit-scrollbar { display: none; }
        #ga-mobile-nav .mob-nav-inner {
            display: flex; white-space: nowrap; padding: 0 12px;
        }
        #ga-mobile-nav a {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 12px 14px; font-size: 13px; font-weight: 500;
            color: var(--muted); text-decoration: none; border-bottom: 2px solid transparent;
            transition: all .15s;
        }
        #ga-mobile-nav a.active { color: var(--accent); border-bottom-color: var(--accent); }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            #ga-topbar .sep,
            #ga-topbar .site     { display: none; }
            #ga-topbar           { padding: 0 16px; }
            #ga-topbar .admin-btn { font-size: 11px; padding: 5px 10px; }

            #ga-sidebar          { display: none; }
            #ga-mobile-nav       { display: block; }

            #ga-layout           { flex-direction: column; }
            #ga-content          { padding: 20px 16px 60px; max-width: 100%; }

            .doc-header h1       { font-size: 22px; }
            .doc-header p        { font-size: 14px; }
            .doc-section h2      { font-size: 15px; }
            .doc-section p,
            .doc-section li      { font-size: 13.5px; }

            .kpi-grid            { grid-template-columns: 1fr 1fr; gap: 10px; }
            .step                { padding: 14px 16px; gap: 12px; }
            .step-num            { width: 28px; height: 28px; font-size: 13px; }
            .step-body h3        { font-size: 13.5px; }
            .step-body p         { font-size: 12.5px; }

            /* Make shared-links table scroll horizontally */
            .shared-links-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .shared-links-scroll table { min-width: 640px; }
        }

        @media (max-width: 400px) {
            .kpi-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div id="ga-topbar">
    <span class="logo">Analytics Docs</span>
    <span class="sep">|</span>
    <span class="site"><?php echo esc_html(get_bloginfo('name')); ?></span>
    <span class="spacer"></span>
    <a class="admin-btn" href="<?php echo esc_url(admin_url('admin.php?page=growtype-analytics')); ?>">
        Dashboard →
    </a>
</div>

<!-- Mobile horizontal tab bar -->
<div id="ga-mobile-nav">
    <div class="mob-nav-inner">
        <?php foreach ($nav as $key => $item): ?>
            <a class="<?php echo $active_page === $key ? 'active' : ''; ?>" href="<?php echo esc_url($this->renderer->page_url($key)); ?>">
                <?php echo $item['icon']; ?> <?php echo esc_html($item['label']); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div id="ga-layout">

    <nav id="ga-sidebar">
        <div class="nav-group-label">Documentation</div>
        <?php foreach ($nav as $key => $item): ?>
            <a class="nav-item <?php echo $active_page === $key ? 'active' : ''; ?>" href="<?php echo esc_url($this->renderer->page_url($key)); ?>">
                <span class="icon"><?php echo $item['icon']; ?></span>
                <?php echo esc_html($item['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <main id="ga-content">
        <?php $this->renderer->render_page_content($active_page); ?>
    </main>

</div>
</body>
</html><?php
    }
}
