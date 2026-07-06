<div class="dukkan-dashboard">

    <!-- Top Banner -->
    <div class="dukkan-top-banner">
        <?php esc_html_e( 'Manage your WooCommerce store through', 'dukkan-plugin' ); ?> 
        <strong><?php esc_html_e( 'Dukkan Admin App', 'dukkan-plugin' ); ?></strong> 🔥
    </div>


    <div class="dukkan-dashboard-layout">

        <!-- Sidebar -->
        <div class="dukkan-wpldp-sidebar">

            <div class="dukkan-brand">
                <img 
                    src="<?php echo DUKKAN_PLUGIN_URL . 'admin/images/dukkan-logo.png'; ?>" 
                    alt="Dukkan"
                    class="dukkan-brand-logo"
                >
            </div>

            <div class="dukkan-wpldp-sidebar-section">
                <h3><?php esc_html_e('Dukkan Mobile', 'dukkan-plugin'); ?></h3>
                <p><?php esc_html_e('WooCommerce on the go', 'dukkan-plugin'); ?></p>
            </div>


            <div class="dukkan-menu">

                <div class="dukkan-menu-item active" data-tab="plugins">
                    <strong><?php esc_html_e('Integrated Plugins', 'dukkan-plugin'); ?></strong>
                    <span><?php esc_html_e('WPML, TranslatePress, Yoast', 'dukkan-plugin'); ?></span>
                </div>

                <div class="dukkan-menu-item" data-tab="download">
                    <strong><?php esc_html_e('Download App', 'dukkan-plugin'); ?></strong>
                    <span><?php esc_html_e('iOS & Android apps', 'dukkan-plugin'); ?></span>
                </div>

                <div class="dukkan-menu-item" data-tab="links">
                    <strong><?php esc_html_e('Quick Links', 'dukkan-plugin'); ?></strong>
                    <span><?php esc_html_e('Docs, tutorials & support', 'dukkan-plugin'); ?></span>
                </div>

                <div class="dukkan-menu-item" data-tab="features">
                    <strong><?php esc_html_e('Dukkan App Features', 'dukkan-plugin'); ?></strong>
                    <span><?php esc_html_e('Complete store management', 'dukkan-plugin'); ?></span>
                </div>

            </div>

        </div>



        <!-- Content -->
        <div class="dukkan-content">

            <!-- Integrated Plugins -->
            <div class="dukkan-tab active" id="dukkan-tab-plugins">

                <h2><?php esc_html_e('Integrated Plugins', 'dukkan-plugin'); ?></h2>

                <p class="dukkan-desc">
                    <?php esc_html_e('Manage your plugins effortlessly with AI-powered assistance — easy, fast, and saves you time', 'dukkan-plugin'); ?>
                </p>


                <div class="dukkan-plugin-grid">

                    <!-- WPML -->
                    <div class="dukkan-plugin-card">

                        <img src="<?php echo DUKKAN_PLUGIN_URL . 'admin/images/wpml.png'; ?>">

                        <h4><?php esc_html_e('WPML', 'dukkan-plugin'); ?></h4>

                        <p>
                            <?php esc_html_e('AI-assisted translation management for 40+ languages.', 'dukkan-plugin'); ?>
                            <?php esc_html_e('Fast, accurate, and effortless multilingual content.', 'dukkan-plugin'); ?>
                        </p>

                        <div class="dukkan-ai">
                            ✓ <?php esc_html_e('AI-Powered', 'dukkan-plugin'); ?>
                        </div>

                    </div>


                    <!-- TranslatePress -->
                    <div class="dukkan-plugin-card">

                        <img src="<?php echo DUKKAN_PLUGIN_URL . 'admin/images/translatepress.png'; ?>">

                        <h4><?php esc_html_e('TranslatePress', 'dukkan-plugin'); ?></h4>

                        <p>
                            <?php esc_html_e('Smart AI translations with real-time preview.', 'dukkan-plugin'); ?>
                            <?php esc_html_e('Quick edits, instant results, minimal time investment.', 'dukkan-plugin'); ?>
                        </p>

                        <div class="dukkan-ai">
                            ✓ <?php esc_html_e('AI-Powered', 'dukkan-plugin'); ?>
                        </div>

                    </div>


                    <!-- Yoast -->
                    <div class="dukkan-plugin-card">

                        <img src="<?php echo DUKKAN_PLUGIN_URL . 'admin/images/yoast.png'; ?>">

                        <h4><?php esc_html_e('Yoast SEO', 'dukkan-plugin'); ?></h4>

                        <p>
                            <?php esc_html_e('AI-driven SEO optimization.', 'dukkan-plugin'); ?>
                            <?php esc_html_e('Auto-suggest keywords, meta titles, and descriptions in seconds.', 'dukkan-plugin'); ?>
                        </p>

                        <div class="dukkan-ai">
                            ✓ <?php esc_html_e('AI-Powered', 'dukkan-plugin'); ?>
                        </div>

                    </div>

                </div>


                <!-- AI Powered Management -->
                <div class="dukkan-ai-management">

                    <div class="dukkan-ai-header">

                        <div class="dukkan-ai-icon">
                            <i class="fa-solid fa-bolt"></i>
                        </div>

                        <div class="dukkan-ai-text">
                            <h3><?php esc_html_e('AI-Powered Management', 'dukkan-plugin'); ?></h3>

                            <p>
                                <?php esc_html_e("Dukkan's intelligent AI assistant helps you manage all integrated plugins with natural language commands.", 'dukkan-plugin'); ?>
                                <?php esc_html_e("Simply tell the app what you need, and it handles the complexity for you.", 'dukkan-plugin'); ?>
                            </p>
                        </div>

                    </div>


                    <div class="dukkan-ai-features">

                        <div class="dukkan-ai-feature">
                            <span class="wpldp-icon green"><i class="fa-solid fa-check"></i></span>
                            <div>
                                <strong><?php esc_html_e('Easy to Use', 'dukkan-plugin'); ?></strong>
                                <p><?php esc_html_e('Simple commands', 'dukkan-plugin'); ?></p>
                            </div>
                        </div>

                        <div class="dukkan-ai-feature">
                            <span class="wpldp-icon blue"><i class="fa-solid fa-clock"></i></span>
                            <div>
                                <strong><?php esc_html_e('Lightning Fast', 'dukkan-plugin'); ?></strong>
                                <p><?php esc_html_e('Instant results', 'dukkan-plugin'); ?></p>
                            </div>
                        </div>

                        <div class="dukkan-ai-feature">
                            <span class="wpldp-icon orange"><i class="fa-solid fa-shield"></i></span>
                            <div>
                                <strong><?php esc_html_e('Time Saver', 'dukkan-plugin'); ?></strong>
                                <p><?php esc_html_e('Less effort needed', 'dukkan-plugin'); ?></p>
                            </div>
                        </div>

                    </div>

                </div>


                <div class="dukkan-view-plugins">
                    <a href="#"><?php esc_html_e('View all supported plugins', 'dukkan-plugin'); ?> →</a>
                </div>



                <hr class="dukkan-divider">


                <!-- App Metrics -->
                <div class="dukkan-app-metrics">

                    <h3><?php esc_html_e('App Metrics', 'dukkan-plugin'); ?></h3>

                    <div class="dukkan-metrics-grid">

                        <div class="dukkan-metric-card">

                            <div class="dukkan-mc-left">
                                <div class="dukkan-metric-icon orange">
                                    <i class="fa-solid fa-download"></i>
                                </div>
                                <h4><?php esc_html_e('Total Downloads', 'dukkan-plugin'); ?></h4>

                                <p><?php esc_html_e('Worldwide app installations', 'dukkan-plugin'); ?></p>
                            </div>

                            <div class="dukkan-metric-info">
                                <span class="dukkan-metric-number">50K+</span>
                            </div>

                        </div>


                        <div class="dukkan-metric-card">

                            <div class="dukkan-mc-left">
                                <div class="dukkan-metric-icon yellow">
                                    <i class="fa-solid fa-star"></i>
                                    
                                </div>
                                <h4><?php esc_html_e('App Rating', 'dukkan-plugin'); ?></h4>

                                <p><?php esc_html_e('Average user rating', 'dukkan-plugin'); ?></p>
                            </div>

                            <div class="dukkan-metric-info">
                                <span class="dukkan-metric-number">4.8★</span>
                            </div>

                        </div>


                        <div class="dukkan-metric-card">

                            <div class="dukkan-mc-left">
                                <div class="dukkan-metric-icon green">
                                    <i class="fa-solid fa-mobile-screen"></i>
                                </div>
                                <h4><?php esc_html_e('Active Users', 'dukkan-plugin'); ?></h4>

                                <p><?php esc_html_e('Monthly active users', 'dukkan-plugin'); ?></p>
                            </div>

                            <div class="dukkan-metric-info">
                                <span class="dukkan-metric-number">10K+</span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>
            

            <div class="dukkan-tab" id="dukkan-tab-download">

                <div class="dukkan-download-wpldp-wrapper">

                    <!-- PHONE MOCKUP -->
                    <div class="dukkan-phone-mockup">

                        <div class="dukkan-phone-notch"></div>

                        <div class="dukkan-phone-screen">

                            <div class="dukkan-phone-top">
                                <span class="dukkan-phone-title"><?php esc_html_e('Dukkan', 'dukkan-plugin'); ?></span>
                                <span class="dukkan-phone-orange"></span>
                            </div>

                            <div class="dukkan-phone-stats">

                                <div class="dukkan-phone-box">
                                    <strong>142</strong>
                                    <span><?php esc_html_e('Products', 'dukkan-plugin'); ?></span>
                                </div>

                                <div class="dukkan-phone-box">
                                    <strong>89</strong>
                                    <span><?php esc_html_e('Orders', 'dukkan-plugin'); ?></span>
                                </div>

                            </div>

                            <div class="dukkan-phone-card">
                                <div class="dukkan-card-icon green"></div>

                                <div>
                                    <strong><?php esc_html_e('New Order', 'dukkan-plugin'); ?></strong>
                                    <small>$124.99</small>
                                </div>
                            </div>

                            <div class="dukkan-phone-card">
                                <div class="dukkan-card-icon yellow"></div>

                                <div>
                                    <strong><?php esc_html_e('Low Stock', 'dukkan-plugin'); ?></strong>
                                    <small>3 <?php esc_html_e('items', 'dukkan-plugin'); ?></small>
                                </div>
                            </div>

                        </div>

                    </div>


                    <!-- CONTENT -->
                    <div class="dukkan-download-content">

                        <h2><?php esc_html_e('Download the Dukkan App', 'dukkan-plugin'); ?></h2>

                        <p class="dukkan-download-desc">
                            <?php esc_html_e('Dukkan is a powerful WooCommerce admin app that lets you manage your entire online store from your mobile device. Track orders, update products, manage inventory, and analyze store performance — all from the palm of your hand.', 'dukkan-plugin'); ?>
                        </p>


                        <ul class="dukkan-download-features">

                            <li><?php esc_html_e('Complete WooCommerce store management', 'dukkan-plugin'); ?></li>
                            <li><?php esc_html_e('Process orders and manage fulfillment on the go', 'dukkan-plugin'); ?></li>
                            <li><?php esc_html_e('Real-time sales analytics and reports', 'dukkan-plugin'); ?></li>
                            <li><?php esc_html_e('Instant notifications for new orders and low stock', 'dukkan-plugin'); ?></li>

                        </ul>


                        <div class="dukkan-download-buttons">

                            <a href="#" class="dukkan-store-btn">
                                <i class="fab fa-apple"></i>
                                <div class="dukkan-store-btn-text">
                                    <?php esc_html_e('Download on the', 'dukkan-plugin'); ?><br>
                                    <strong><?php esc_html_e('App Store', 'dukkan-plugin'); ?></strong>
                                </div>
                            </a>

                            <a href="#" class="dukkan-store-btn">
                                <i class="fab fa-google-play"></i>
                                <div class="dukkan-store-btn-text">
                                   <?php esc_html_e('GET IT ON', 'dukkan-plugin'); ?><br>
                                    <strong><?php esc_html_e('Google Play', 'dukkan-plugin'); ?></strong>
                                </div>
                                
                            </a>

                        </div>

                    </div>

                </div>

            </div>


            <div class="dukkan-tab" id="dukkan-tab-links">

                <div class="dukkan-quick-links">

                    <h2><?php esc_html_e('Quick Links', 'dukkan-plugin'); ?></h2>
                    <p class="dukkan-links-desc">
                        <?php esc_html_e('Access helpful resources and support', 'dukkan-plugin'); ?>
                    </p>

                    <div class="dukkan-links-grid">

                        <a target="_blank" href="#" class="dukkan-link-card">
                            <span><?php esc_html_e('Documentation', 'dukkan-plugin'); ?></span>
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>

                        <a target="_blank" href="#" class="dukkan-link-card">
                            <span><?php esc_html_e('Video Tutorials', 'dukkan-plugin'); ?></span>
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>

                        <a target="_blank" href="#" class="dukkan-link-card">
                            <span><?php esc_html_e('Support Forum', 'dukkan-plugin'); ?></span>
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>

                        <a target="_blank" href="#" class="dukkan-link-card">
                            <span><?php esc_html_e('Feature Requests', 'dukkan-plugin'); ?></span>
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>

                        <a target="_blank" href="#" class="dukkan-link-card">
                            <span><?php esc_html_e('API Documentation', 'dukkan-plugin'); ?></span>
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>

                        <a target="_blank" href="#" class="dukkan-link-card">
                            <span><?php esc_html_e('Release Notes', 'dukkan-plugin'); ?></span>
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>

                    </div>

                </div>

            </div>


            <div class="dukkan-tab" id="dukkan-tab-features">

                <div class="dukkan-features-section">

                    <h2><?php esc_html_e('Manage Everything Mobile', 'dukkan-plugin'); ?></h2>

                    <div class="dukkan-features-grid">

                        <!-- Product Management -->
                        <div class="dukkan-feature-card">

                            <div class="dukkan-feature-icon product">
                                <i class="fa-solid fa-cube"></i>
                            </div>

                            <h3><?php esc_html_e('Product Management', 'dukkan-plugin'); ?></h3>

                            <p>
                                <?php esc_html_e('Add, edit, and manage products with images, pricing, inventory, and variations from your mobile device.', 'dukkan-plugin'); ?>
                            </p>

                        </div>


                        <!-- Orders Management -->
                        <div class="dukkan-feature-card">

                            <div class="dukkan-feature-icon orders">
                                <i class="fa-solid fa-cart-shopping"></i>
                            </div>

                            <h3><?php esc_html_e('Orders Management', 'dukkan-plugin'); ?></h3>

                            <p>
                                <?php esc_html_e('Process orders, update statuses, manage refunds, and communicate with customers in real-time.', 'dukkan-plugin'); ?>
                            </p>

                        </div>


                        <!-- Analytics -->
                        <div class="dukkan-feature-card">

                            <div class="dukkan-feature-icon analytics">
                                <i class="fa-solid fa-chart-column"></i>
                            </div>

                            <h3><?php esc_html_e('Full Store Analysis', 'dukkan-plugin'); ?></h3>

                            <p>
                                <?php esc_html_e('Track sales, revenue, top products, and customer insights with comprehensive analytics dashboards.', 'dukkan-plugin'); ?>
                            </p>

                        </div>


                        <!-- Coupons -->
                        <div class="dukkan-feature-card">

                            <div class="dukkan-feature-icon coupons">
                                <i class="fa-solid fa-ticket"></i>
                            </div>

                            <h3><?php esc_html_e('Coupons Management', 'dukkan-plugin'); ?></h3>

                            <p>
                                <?php esc_html_e('Create, edit, and manage discount codes, promotional campaigns, and coupon usage tracking.', 'dukkan-plugin'); ?>
                            </p>

                        </div>

                        <!-- Bulk Products Edit -->
                        <div class="dukkan-feature-card">

                            <div class="dukkan-feature-icon bulk">
                                <i class="fa-solid fa-pen"></i>
                            </div>

                            <h3><?php esc_html_e('Bulk Products Edit', 'dukkan-plugin'); ?></h3>

                            <p>
                                <?php esc_html_e('Update multiple products simultaneously—change prices, stock levels, and attributes in bulk.', 'dukkan-plugin'); ?>
                            </p>

                        </div>


                        <!-- Categories & Tags -->
                        <div class="dukkan-feature-card">

                            <div class="dukkan-feature-icon categories">
                                <i class="fa-solid fa-folder-tree"></i>
                            </div>

                            <h3><?php esc_html_e('Categories & Tags', 'dukkan-plugin'); ?></h3>

                            <p>
                                <?php esc_html_e('Organize your store with categories and tags. Create hierarchies and manage product taxonomies.', 'dukkan-plugin'); ?>
                            </p>

                        </div>

                    </div>

                </div>

            </div>


        </div>

    </div>

</div>