<?php
/**
 * Documentation class for Bbh Redirection.
 *
 * @package Bbhre_Redirection
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBHRE_Documentation {

    public static function render() {
        ?>
        <div class="bbhredh-wrap">
            <div class="bbgredreportpagehead">
                <h1><?php esc_html_e( 'BBH Redirection Documentation', 'bbh-redirection' ); ?></h1>
                <p class="bbhre-postbox"><?php esc_html_e( 'Welcome to the documentation for BBH Redirection! This plugin provides a simple and efficient way to manage 301 redirects on your WordPress site.', 'bbh-redirection' ); ?></p>
            </div>
            <div class="bbh-redirection-full-page">
                <div class="bbh-redirection-content">
                    <div class="postbox bbhredpostbox">
                        <h2 id="bbhred-title"><?php esc_html_e( 'Getting Started', 'bbh-redirection' ); ?></h2>
                        <p class="bbhre-postbox"><?php esc_html_e( 'To get started with BBH Redirection, follow these steps:', 'bbh-redirection' ); ?></p>
                        <ol>
                            <li><?php esc_html_e( 'Install and activate the BBH Redirection plugin from the WordPress plugin repository.', 'bbh-redirection' ); ?></li>
                            <li><?php esc_html_e( 'Navigate to the "301 Redirection" menu in your WordPress admin dashboard.', 'bbh-redirection' ); ?></li>
                            <li><?php esc_html_e( 'Click on "Add New Redirect" to create a new redirect rule.', 'bbh-redirection' ); ?></li>
                            <li><?php esc_html_e( 'Fill in the source URL (the URL you want to redirect) and the target URL (the URL you want to redirect to).', 'bbh-redirection' ); ?></li>
                            <li><?php esc_html_e( 'Save your changes.', 'bbh-redirection' ); ?></li>
                        </ol>
                    </div>
                    <div class="postbox bbhredpostbox">
                        <h2 id="bbhred-title"><?php esc_html_e( 'Best Practices', 'bbh-redirection' ); ?></h2>
                        <p class="bbhre-postbox"><?php esc_html_e( 'Here are some best practices to keep in mind when using BBH Redirection:', 'bbh-redirection' ); ?></p>
                        <ul>
                            <li><?php esc_html_e( 'Always use 301 redirects for permanent changes to ensure search engines update their indexes.', 'bbh-redirection' ); ?></li>
                            <li><?php esc_html_e( 'Regularly review your redirect rules to ensure they are still relevant and necessary.', 'bbh-redirection' ); ?></li>
                            <li><?php esc_html_e( 'Use descriptive source URLs to make it easier to manage your redirects.', 'bbh-redirection' ); ?></li>
                            <li><?php esc_html_e( 'Monitor your redirect logs to identify any potential issues or patterns.', 'bbh-redirection' ); ?></li>
                        </ul>
                    </div>
                    <div class="postbox bbhredpostbox">
                        <h2 id="bbhred-title"><?php esc_html_e( 'Features', 'bbh-redirection' ); ?></h2>
                        <p class="bbhre-postbox"><?php esc_html_e( 'Here are some features of BBH Redirection:', 'bbh-redirection' ); ?></p>
                        <ul>
                            <li><?php esc_html_e( 'Simple and intuitive admin interface for managing redirects.', 'bbh-redirection' ); ?></li>
                            <li><?php esc_html_e( 'Supports 301 permanent redirects.', 'bbh-redirection' ); ?></li>
                        </ul>
                    </div>
                </div>
                <div class="bbh-redirection-sidebar">
                    <div class="postbox bbhredpostbox">
                        <h2 id="bbhred-title">Plugin Information</h2>
                        <p class="bbhre-postbox">
                            <strong>Version:</strong> <?php echo esc_html( BBH_REDIRECTION_VERSION ); ?><br>
                            <strong>Author:</strong> <a href="https://profiles.wordpress.org/jahidshah/#content-plugins" target="_blank" rel="noopener noreferrer">Md Jahid Shah</a><br>
                            <strong>License:</strong> GPL v2 or later<br>
                        </p>
                    </div>
                        
                    <div class="postbox bbhredpostbox">
                        <h2 id="bbhred-title">About Author</h2>
                        <div class="bbhbred-author-box">
                            <div class="plugin-author-img"></div>
                            <p class="bbhre-postbox">
                                I'm <strong><a href="https://jahidshah.com/" target="_blank" rel="noopener noreferrer">Jahid Shah</a></strong>,
                                a front-end developer with specialized skills in WordPress theme development and WordPress Security.
                                I’m passionate about creating error-free, secure websites and achieving 100% client satisfaction.
                                Solving real-world problems is my passion.
                            </p>
                            <div>
                                <p class="bbhre-postbox bbh-bmc-btn">
                                    If you found this plugin helpful, you can support the developer via -<br><a href="https://www.buymeacoffee.com/jahidshah" target="_blank" rel="noopener noreferrer">Buy Me a Coffee</a>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="postbox bbhredpostbox">
                        <h2 id="bbhred-title">Watch Help Video</h2>
                        <p class="bbhre-postbox"><a href="" target="_blank" class="bbhcshyt-btn">Watch On YouTube</a></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}       