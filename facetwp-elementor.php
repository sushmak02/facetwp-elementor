<?php
/*
Plugin Name: FacetWP - Elementor
Description: FacetWP and Elementor Integration
Version: 1.0
Author: FacetWP, LLC
Author URI: https://facetwp.com/
GitHub URI: facetwp/facetwp-elementor
*/

defined( 'ABSPATH' ) or exit;

class FacetWP_Elementor_Addon {

    private static $instance;
    private $elements;
    private $is_elementor = false;
    private $is_pro = false;


    /**
     * Constructor
     */
    function __construct() {

        // setup variables
        define( 'FACETWP_ELEMENTOR_VERSION', '1.0' );

        // get the gears turning
        add_action( 'elementor_pro/init', [ $this, 'setup_elementor' ] );

    }


    /**
     * Singleton instance
     */
    public static function init() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     * Setup hooks and properties for Elementor Pro
     */
    function setup_elementor() {

        $this->is_pro = defined( 'ELEMENTOR_PRO_VERSION' );
        $this->elements = apply_filters( 'facetwp_elementor_elements', [
                'posts',
                'archive-posts',
                'woocommerce-products',
                'woocommerce-archive-products',
                'uael-posts',
                'uael-woo-products',
            ]
        );

        add_filter( 'pre_get_posts', [ $this, 'check_current_page' ], 1 );
        add_filter( 'facetwp_is_main_query', [ $this, 'is_main_query' ], 10, 2 );
        add_action( 'elementor/widget/before_render_content', [ $this, 'add_template_class' ] );
        add_filter( 'facetwp_assets', [ $this, 'front_scripts' ] );

        // Hook only into desired element types (performance boost)
        $sections = [
            'section_layout' => [
                'posts',
                'archive-posts',
            ],
            'section_content' => [
                'woocommerce-products',
                'woocommerce-archive-products',
            ],
            'section_filter_field' => [
                'uael-woo-products',
                'uael-posts',
            ]
        ];

        foreach ( $sections as $section_id => $widgets ) {
            foreach ( $widgets as $widget ) {
                add_action( "elementor/element/{$widget}/{$section_id}/after_section_end", [ $this, 'register_controls' ], 10, 2 );
            }
        }
    }


    /**
     * Is Elementor being used on this page?
     */
    function check_current_page( $query ) {

        if ( ! $this->is_elementor ) {

            if ( \Elementor\Plugin::$instance->db->is_built_with_elementor( get_queried_object_id() ) ) {
                $this->is_elementor = true;
            }
            elseif ( is_archive() || is_tax() || is_home() || is_search() ) {

                if ( $this->is_pro ) {
                    $location = 'archive';
                    $location_documents = \ElementorPro\Plugin::instance()->modules_manager->get_modules('theme-builder')->get_conditions_manager()->get_documents_for_location( $location );
                    if ( ! empty( $location_documents ) ) {
                        $this->is_elementor = true;
                    }
                }
            }
        }
    }


    /**
     * Add an "Enable FacetWP" toggle for supported module types
     */
    function register_controls( $element, $args ) {
        $element->start_controls_section(
            'facetwp_section', [
                'label' => __( 'FacetWP', 'facetwp-elementor' ),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $element->add_control(
            'enable_facetwp', [
                'label' => __( 'Enable FacetWP', 'facetwp-elementor' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'facetwp-elementor' ),
                'label_off' => __( 'No', 'facetwp-elementor' ),
                'return_value' => 'yes',
                'default' => 'no'
            ]
        );

        $element->end_controls_section();
    }


    /**
     * Use FacetWP for the current query?
     */
    function is_main_query( $is_main_query, $query ) {

        if ( ( is_archive() || is_tax() || is_home() || is_search() ) && ! ( function_exists( 'is_shop' ) && is_shop() ) ) {
            return $is_main_query;
        }

        if ( $this->is_elementor && true != $query->get( 'facetwp' ) ) {
            $is_main_query = false;
        }

        return $is_main_query;
    }


    /**
     * Add the "facetwp-template" CSS class if needed
     */
    function add_template_class( $widget ) {
        if ( in_array( $widget->get_name(), $this->elements ) ) {
            $settings = $widget->get_settings();
        
            if ( ! empty( $settings['enable_facetwp'] && 'yes' == $settings['enable_facetwp'] ) ) {

                $widget->add_render_attribute( '_wrapper', 'class', [ 'facetwp-template' ] );

                if ( empty( $settings['posts_query_id'] ) ) {

                    $settings['posts_query_id'] = 'facetwp_query';
                    $widget->set_settings( $settings );
                }

                if ( 'posts' == $widget->get_name() ) {

                    add_action( "elementor_pro/posts/query/{$settings['posts_query_id']}", function( $query, $widget ) {
                        $query->set( 'facetwp', true );
                    }, 10, 2 );

                }
                elseif ( in_array( $widget->get_name(), [ 'woocommerce-archive-products', 'woocommerce-products', 'uael-posts', 'uael-woo-products' ] ) ) {

                    add_filter( 'pre_get_posts', function( $query ) {
                        $query->set( 'facetwp', true );
                    });

                }
                else {
                    do_action( 'facetwp_elementor_query', $widget->get_name() );
                }
            }
        }
    }


    /**
     * Load FacetWP's assets if needed
     */
    function front_scripts( $assets ) {
        if ( $this->is_elementor ) {
            $assets['facetwp-elementor-front.js'] = plugins_url( '', __FILE__ ) . '/assets/js/front.js';
        }
        return $assets;
    }
}


FacetWP_Elementor_Addon::init();
