<?php
/**
 * Created by simon
 * Date: 19.01.2021
 */

namespace ShortPixel\AI;

class VanillaJsLoader extends JsLoader {

    protected function __construct() {
        parent::__construct();
    }

    public function enqueue()
    {
        add_action( 'wp_head', function() {
            $apiUrlParts = explode('/', rtrim($this->settings->behaviour->api_url, '/'));
            $convert = 'none';
            if(!!$this->settings->compression->webp || !!$this->settings->compression->avif) {
                if (!$this->ctrl->varyCacheSupport) {
                    $convert = 'detect';
                } elseif(!!$this->settings->compression->webp && !!$this->settings->compression->avif) {
                    $convert = 'auto';
                } elseif (!!$this->settings->compression->webp) {
                    $convert = 'webp';
                } else {
                    $convert = 'avif';
                }
            }
            $dbg = (SHORTPIXEL_AI_DEBUG || isset($_GET['SPAI_VJS']));
            $vjsVer = isset($_GET['SPAI_VJS']) ? $_GET['SPAI_VJS'] : SHORTPIXEL_AI_VANILLAJS_VER;
            $spaiDomain = parse_url($this->settings->behaviour->api_url, PHP_URL_HOST);
            $scriptDomain = ($dbg ? 'dev.shortpixel.ai' : $spaiDomain);
            ?>
            <script type="text/javascript" id="spai_js" data-cfasync="false">
                document.documentElement.className += " spai_has_js";
                (function(w, d){
                    var b = d.getElementsByTagName('head')[0];
                    var s = d.createElement("script");
                    var v = ("IntersectionObserver" in w) ? "" : "-compat";
                    s.async = true; // This includes the script as async.
                    //s.src = "https://dev.shortpixel.ai/assets/js/spai-lib-bg" + v + ".js";
                    s.src = "https://<?= $scriptDomain ?>/assets/js/bundles/spai-lib-bg<?= $convert === 'detect' ? '-webp' : '' ?>" + v
                        + ".<?=$vjsVer?><?=($dbg ? '.dev' : '')?>.min.js?v=<?= SHORTPIXEL_AI_VERSION ?>";
                    w.spaiDomain = "<?= $spaiDomain ?>";
                    w.spaiData = {
                        version: "<?= SHORTPIXEL_AI_VERSION ?>",
                        key: "<?= end($apiUrlParts)?>",
                        quality: "<?= $this->settings->compression->level ?>",
                        convert: "<?= $convert ?>",
                        lqip: <?= $this->settings->behaviour->lqip ? 'true' : 'false' ?>,
                        <?php
                            if(!!$this->settings->compression->webp && !(!!$this->settings->compression->png_to_webp && !!$this->settings->compression->jpg_to_webp && !!$this->settings->compression->gif_to_webp)) {
                                //means at least one of the three is deactivated for conversion
                                ?>
                        converts: {
                            png: "<?= !!$this->settings->compression->png_to_webp ? $convert : 'none' ?>",
                            jpg: "<?= !!$this->settings->compression->jpg_to_webp ? $convert : 'none' ?>",
                            gif: "<?= !!$this->settings->compression->gif_to_webp ? $convert : 'none' ?>",
                        },
                                <?php
                            }
                        ?>
                        rootMargin: "<?= (int) is_int( $this->settings->behaviour->lazy_threshold ) && $this->settings->behaviour->lazy_threshold >= 0 ? $this->settings->behaviour->lazy_threshold : 500 ?>px",
                        crop: <?= !!$this->settings->behaviour->crop ? 'true' : 'false' ?>,
                        sizeBreakpoints: <?= json_encode((object)['on' => $this->settings->behaviour->size_breakpoints, 'base' => $this->settings->behaviour->size_breakpoints_base, 'rate' => $this->settings->behaviour->size_breakpoints_rate]); ?>,
                        backgroundsMaxWidth: <?= (int) is_int( $this->settings->areas->backgrounds_max_width ) && $this->settings->areas->backgrounds_max_width >= 0 ? $this->settings->areas->backgrounds_max_width : 1920 ?>,
                        debug: <?= SHORTPIXEL_AI_DEBUG ? 'true' : 'false' ?>,
                        <?php
                        //NOT used any more, replaced by doSelectors. TODO remove
                        //backgroundReplaceClasses: ["__SPAI_BACKGROUND_REPLACE_CLASSES__"],
                        /*backgroundReplaceTags: "__SPAI_BACKGROUND_LAZY_TAGS__",*/
                        //customTagsAttributes: "__SPAI_CUSTOM_TAGS_ATTRIBUTES__",
                        //backgroundLazySelectors: "__SPAI_BACKGROUND_LAZY_SELECTORS__",
                        ?>
                        doSelectors: "__SPAI_DO_SELECTORS__",
                        exclusions: "__SPAI_EXCLUSIONS__",
                        watchClasses: [],
                        sizeFromImageSuffix: <?php echo(defined('SPAI_FILENAME_RESOLUTION_UNSAFE') ? 'false' : 'true'); ?>
                    };
                    b.appendChild(s);
                }(window, document));
            </script>
            <?php
        } );

        if(\ShortPixelAI::userCan( 'manage_options' )) {
            wp_register_script( 'spai-snip-action', '', [], '', true );
            wp_localize_script( 'spai-snip-action', 'spai_settings', [
                'api_domain'            =>  parse_url($this->settings->behaviour->api_url, PHP_URL_HOST),
                'ajax_url'              => admin_url( 'admin-ajax.php' ),
                'excluded_selectors'    => $this->ctrl->splitSelectors( $this->settings->exclusions->excluded_selectors, ',' ),
                'eager_selectors'       => $this->ctrl->splitSelectors( $this->settings->exclusions->eager_selectors, ',' ),
                'noresize_selectors'    => $this->ctrl->splitSelectors( $this->settings->exclusions->noresize_selectors, ',' ),
                'excluded_paths'        => array_map( 'base64_encode', $this->ctrl->splitSelectors( $this->settings->exclusions->excluded_paths, PHP_EOL ) ),
            ]);
            wp_enqueue_script( 'spai-snip-action'  );
            wp_add_inline_script('spai-snip-action',
            "function spaiSniperClick() {
                if(typeof ShortPixelAI === 'undefined') {
                    window.ShortPixelAI = {};
                }
                if(typeof ShortPixelAI.NORESIZE === 'undefined') {
                    ShortPixelAI.NORESIZE = 1;
                    ShortPixelAI.EXCLUDED = 2;
                    ShortPixelAI.EAGER = 4;
                    ShortPixelAI.is = function(elm, types) {
                        var excluded = 0;
                        if(types & ShortPixelAI.EAGER) {
                            for(var i = 0; i < spai_settings.eager_selectors.length; i++) { //.elementor-section-stretched img.size-full
                                var selector = spai_settings.eager_selectors[i];
                                try {if(elm.is(selector)) excluded |= ShortPixelAI.EAGER;} catch (xc){} //we don't bother about wrong selectors at this stage
                            }
                        }
                    
                        if(types & ShortPixelAI.EXCLUDED) {
                            for(var i = 0; i < spai_settings.excluded_selectors.length; i++) { //.elementor-section-stretched img.size-full
                                var selector = spai_settings.excluded_selectors[i];
                                try {if(elm.is(selector)) excluded |= ShortPixelAI.EXCLUDED;} catch (xc){}
                            }
                        }
                    
                        if(types & ShortPixelAI.NORESIZE) {
                            for(var i = 0; i < spai_settings.noresize_selectors.length; i++) { //.elementor-section-stretched img.size-full
                                var selector = spai_settings.noresize_selectors[i];
                                try {if(elm.is(selector)) excluded |= ShortPixelAI.NORESIZE;} catch (xc){}
                            }
                        }
                        return excluded;
                    };
                }
                SpaiSniper(1);
            }
            ");
        }

        if(ActiveIntegrations::_()->has('wp-rocket')) {
            add_filter('rocket_defer_inline_exclusions', [$this, 'wp_rocket_no_defer_spai_settings']);
        }

        parent::enqueue();
    }

    public function wp_rocket_no_defer_spai_settings($regex) {
        if( is_string( $regex ) ){
            return $regex . '|spai_js';
        }
        $regex[] = 'spai_js';
        return $regex;
    }

    public function addTagData($content)
    {
        //$affectedTags = $this->ctrl->affectedTags->getAll();
        //$content = str_replace( '"__SPAI_BACKGROUND_REPLACE_CLASSES__"', '', $content );
        //$content = str_replace( '__SPAI_BACKGROUND_LAZY_SELECTORS__', implode(',', array_keys($affectedTags)), $content );
        $doSelectors = $this->getDoSelectors();
        $this->logger->log("DO SELECTORS: ", $doSelectors);
        if(strpos($content, '"__SPAI_DO_SELECTORS__"') === false) {
            //inline JS was already extracted in a JS file by WPRocket or another page speed tool
            $content = str_replace( '</body>', '<script id="spai_js">var spai_doSelectors=' .  json_encode( $doSelectors )
                . ';var spai_exclusions=' . $this->getExclusions() . ';</script></body>', $content );
        } else {
            $content = str_replace( '"__SPAI_DO_SELECTORS__"', json_encode($doSelectors), $content );
            $content = str_replace( '"__SPAI_EXCLUSIONS__"', $this->getExclusions(), $content );
        }

        $bgCss = '';
        foreach($doSelectors as $rule) {
            $rule = (object)$rule;
            $this->logger->log("DO SELECTORS RULE: ", $rule);
            if($rule->type == '__stylesheet') {
                (SHORTPIXEL_AI_DEBUG & \ShortPixelAILogger::DEBUG_AREA_CSS) && $this->logger->log("CSS FILES ON");
                $bgCss = 'html.spai_has_js :not(.spai-bg-prepared)';
                break;
            }/*
            elseif($rule->type == 'style'){
                $filter = $rule['filter']['classes'];
                if(isset($rule['filter']['attrName'])) {
                    $filter .= '[' . $rule['filter']['attrName'] . (isset($rule['filter']['attrVal']) ? '="' . $rule['filter']['attrVal'] . '"' : '') . ']';
                }
                foreach($rule['selectors'] as $sel) {
                    $bgCss .= 'html.spai_has_js ' . $sel . $filter . ':not(.spai-bg-prepared),';
                }
            }*/
        }
        if(!strlen($bgCss)) {
            $bgCss .= 'html.spai_has_js .spai-bg-on:not(.spai-bg-prepared)';
        }
        (SHORTPIXEL_AI_DEBUG & \ShortPixelAILogger::DEBUG_AREA_CSS) && $this->logger->log("BACKGROUND CSS RULES: " . $bgCss);

        if(strlen($bgCss)) {
            $content = str_replace( '</head>', '<style id="spai_bg_lazr">' . $bgCss . '{background-image: none !important;}</style></head>', $content);
        }
        //$content = str_replace( '__SPAI_BACKGROUND_LAZY_TAGS__', implode(',', array_keys($affectedBkTags)), $content );
        return $content;
    }

    protected function getDoSelectors() {
        //$lazyRules = TagRules::_()->usedLazy();
        (SHORTPIXEL_AI_DEBUG & \ShortPixelAILogger::DEBUG_AREA_CSS) && $this->logger->log("AFFECTED TAGS: ", $this->ctrl->affectedTags->getAll());
        $doSelectors = [];
        if(count($this->ctrl->affectedTags->filter(AffectedTags::SRC_ATTR))) {
            //add other monkeys like video poster here, if they were found when parsing the page.
            foreach(TagRules::_()->usedLazy() as $rule) {
                (SHORTPIXEL_AI_DEBUG & \ShortPixelAILogger::DEBUG_AREA_HTML) && $this->logger->log("RULE: ", $rule);
                if($rule->type == 'url') {
                    $selector = [
                        'selectors' => implode(',', array_keys($rule->used)),
                        'type' => 'attr',
                        'targetAttr' => $rule->attr,
                        'attrType' => $rule->callback == 'replace_custom_json_attr' ? 'json': 'url',
                        'lazy' => !$rule->frontEager
                    ];
                    $doSelectors[] = $selector;
                }
            }
        }
        if(count($this->ctrl->affectedTags->filter(AffectedTags::CSS_ATTR))) {
            (SHORTPIXEL_AI_DEBUG & \ShortPixelAILogger::DEBUG_AREA_HTML) && $this->logger->log("IN TAG STYLE RULE: .spai-bg-on");
            $doSelectors[] = [
                'selectors' => '.spai-bg-on',
                'type' => 'attr',
                'targetAttr' => 'style',
                'attrType' => 'style',
            ];
        }
        if($this->settings->areas->backgrounds_lazy_style || $this->settings->areas->parse_css_files) {
            (SHORTPIXEL_AI_DEBUG & \ShortPixelAILogger::DEBUG_AREA_HTML) && $this->logger->log("INLINE STYLE RULE");
            $doSelectors[] = [
                'selectors' => 'style',
                'type' => 'inner',
                'attrType' => 'style'
            ];
        }
        if($this->settings->areas->parse_css_files) {
            (SHORTPIXEL_AI_DEBUG & \ShortPixelAILogger::DEBUG_AREA_HTML) && $this->logger->log("STYLESHEET RULE");
            $doSelectors[] = ['type' => '__stylesheet'];
        }
        return $doSelectors;
    }

    protected function getExclusions() {
        //"div.excluded-lazy-lqip" : { lazy: 1, cdn:0, resize:0, lqip: 0, crop:-1},
        $exclusions = ['selectors' => [], 'urls' => []];
        foreach($this->ctrl->splitSelectors( $this->settings->exclusions->excluded_paths, PHP_EOL) as $excludedPath) {
            $exclusions['urls'][$excludedPath] = ['lazy' => 0, 'cdn' => 0, 'resize' => 0, 'lqip' => 0, 'crop' => -1];
        }
        foreach($this->ctrl->splitSelectors( $this->settings->exclusions->excluded_selectors, ',') as $excludedSel) {
            $exclusions['selectors'][$excludedSel] = ['lazy' => 0, 'cdn' => 0, 'resize' => 0, 'lqip' => 0, 'crop' => -1];
        }
        foreach($this->ctrl->splitSelectors( $this->settings->exclusions->noresize_selectors, ',') as $noresizeSel) {
            $exclusions['selectors'][$noresizeSel] = ['lazy' => 1, 'cdn' => 1, 'resize' => 0, 'lqip' => 0, 'crop' => -1];
        }
        foreach($this->ctrl->splitSelectors( $this->settings->exclusions->eager_selectors, ',') as $eagerSel) {
            $exclusions['selectors'][$eagerSel] = ['lazy' => 0, 'cdn' => 1, 'resize' => 1, 'lqip' => 0, 'crop' => 0];
        }

        return json_encode($exclusions);
    }



    public function check()
    {
        return true;
    }

    public function fadeInCss()
    {
        /*
        wp_register_style( 'spai-fadein', false );
        wp_enqueue_style( 'spai-fadein' );
        //Exclude the .zoomImg's as it conflicts with rules of WooCommerce.
        wp_add_inline_style( 'spai-fadein',
            'html.DATA_SPAI_PLACEHOLDER_CLASS{background-image: none !important;}');
        */
    }
}