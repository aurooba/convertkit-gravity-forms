<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

GFForms::include_feed_addon_framework();

class GFConvertKit extends GFFeedAddOn {

	protected $_full_path                = CKGF_PLUGIN_FILEPATH;
	protected $_min_gravityforms_version = CKGF_MIN_GF_VERSION;
	protected $_path                     = CKGF_PLUGIN_BASENAME;
	protected $_short_title              = CKGF_SHORT_TITLE;
	protected $_slug                     = CKGF_SLUG;
	protected $_title                    = CKGF_TITLE;
	protected $_version                  = CKGF_VERSION;

	/** @var null  */
	private static $instance = null;

	/**
	 * Initialize this class
	 */
	public function init() {
		parent::init();
		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Send to ConvertKit only when payment is received.', 'convertkit' ),
			)
		);
	}

	/**
	 * Get singleton
	 *
	 * @return GFConvertKit|null
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new GFConvertKit();
		}

		return self::$instance;
	}

	/**
	 * Output the ConvertKit settings in the Gravity Forms General Settings page
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		ob_start();
		include( 'views/section.php' );
		$section_description = ob_get_clean();

		return array(
			array(
				'title'       => '',
				'description' => $section_description,
				'fields'      => array(
					array(
						'name'              => 'api_key',
						'label'             => __( 'ConvertKit API Key' ),
						'type'              => 'text',
						'class'             => 'medium',
						'required'          => true,
						'feedback_callback' => array( $this, 'validate_api_key' ),
					),
				),
			),
		);
	}

	/**
	 * Check API key by using it in an API call
	 *
	 * @param $api_key
	 *
	 * @return bool
	 */
	public function validate_api_key( $api_key ) {
		return ! is_wp_error( ckgf_convertkit_api_get_forms( $api_key ) );
	}

	/**
	 * Add settings to the form specific Settings tab
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		$base_fields = array(
			'title'       => __( 'ConvertKit Feed Settings' ),
			'description' => '',
			'fields'      => array(
				array(
					'name'     => 'feed_name',
					'label'    => __( 'Name' ),
					'type'     => 'text',
					'class'    => 'medium',
					'required' => true,
					'tooltip'  => sprintf( '<h6>%s</h6>%s', __( 'Name' ), __( 'Enter a feed name to uniquely identify this setup.', 'convertkit' ) ),
				),
				array(
					'name'     => 'form_id',
					'label'    => __( 'ConvertKit Form' ),
					'type'     => 'convertkit_form',
					'required' => true,
					'tooltip'  => sprintf( '<h6>%s</h6>%s', __( 'ConvertKit Form', 'convertkit' ), __( 'Select the ConvertKit form that you would like to add your contacts to.', 'convertkit' ) ),
				),
				array(
					'name'      => 'field_map',
					'label'     => __( 'Map Fields' ),
					'type'      => 'field_map',
					'field_map' => array(
						array(
							'name'       => 'e',
							'label'      => __( 'Email' ),
							'required'   => true,
							'field_type' => '',
						),

						array(
							'name'       => 'n',
							'label'      => __( 'Name' ),
							'required'   => true,
							'field_type' => '',
						),
					),
					'tooltip'   => sprintf( '<h6>%s</h6>%s', __( 'Map Fields', 'convertkit' ), __( 'Associate email address and subscriber name with the appropriate Gravity Forms fields.', 'convertkit' ) ),
				),
				array(
					'name' => 'convertkit_custom_fields',
					'label' => '',
					'type' => 'dynamic_field_map',
					'field_map' => $this->get_custom_fields(),
					'disable_custom' => true,
				),
				array(
					'name'      => 'convertkit_custom_tags',
					'label'     => __( 'Conditional Tagging' ),
					'type'      => 'convertkit_custom_tags',
					'callback'  => array( $this, 'get_conditional_tags_fields' ),
					'validation_callback' => array( $this, 'validate_conditional_tags' ),
					'disable_custom' => true,
					'tooltip'   => sprintf( '<h6>%s</h6>%s', __( 'Conditional Tagging', 'convertkit' ), __( 'Tag a customer when the condition is true.', 'convertkit' ) ),
				),
				array(
					'name'    => 'conditions',
					'label'   => __( 'Conditional Logic' ),
					'type'    => 'feed_condition',
					'tooltip' => sprintf( '<h6>%s</h6>%s', __( 'Conditional Logic', 'convertkit' ), __( 'When conditional logic is enabled, form submissions will only be exported to ConvertKit when the conditions are met. When disabled all form submissions will be exported.', 'convertkit' ) ),
				),
			),
		);

		return array( $base_fields );
	}

	public function get_conditional_tags_fields() {

		$form_id =rgempty( 'id', $_GET ) ? false : rgget( 'id' );
		$feed_id =rgempty( 'fid', $_GET ) ? false : rgget( 'fid' );

		$tags = $this->get_conditional_tags( $feed_id );


		if ( $form_id ) {

			$form = $this->get_current_form();

			// Right now we're only concerned with checkbox fields.
			$checkbox_fields = $this->get_checkbox_fields( $form['fields'] );

			// Build the output
			$html = '';
			$html .= '<div id="feed__logic_container" style="display: block;">';
			$html .= '<table class="form-table gforms_form_settings settings-field-map-table">';
			$html .= '<thead><tr><th>If checkbox is checked...</th><th>add this tag</th></tr></thead>';
			$html .= '<tbody>';

			if ( 0 < count( $checkbox_fields ) ) {
				foreach ( $checkbox_fields as $field ) {
					foreach ( $field['choices'] as $choice ) {
						$html .= '<tr>';
						$html .= '<td>';

						$html .= $choice['text'];
						$html .= '</td>';

						$html .= '<td>';
						$tags = $this->get_tags();

						if ( 0 < count( $tags ) ) {
							$select_id = '_gaddon_setting_convertkit_custom_tags_value_' . $field['id'];
							$html .= '<select name="'. $select_id . '" class="key gaddon-setting gaddon-select">';
							foreach ( $tags as $tag ) {
								$html .= '<option value="' . $tag['id'] . '">' . $tag['name'] . '</option>';
							}
							$html .= '</select>';
						} else{
							$html .= 'No tags found for account';
						}

						$html .= '</td>';
						$html .= '</tr>';
					}
				}
			}

			$html .= '</tbody>';
			$html .= '</table>';

			$html .= '</div>';

			echo $html;

		} else {
			echo 'There was an error getting the form.';
		}
	}


	public function validate_conditional_tags( $field, $field_setting ) {

	    //$checkbox_fields = $this->get_checkbox_fields( );
		error_log( "VALIDATION" );
		error_log( "checkbox: "  );
		error_log( print_r ($field_setting, true ) );

		$feed_id = '';
		$tags = array();
		$this->save_conditional_tags( $feed_id, $tags );
	}

	public function save_conditional_tags( $feed_id, $tags ) {
		update_option( '_gaddon_convertkit_custom_tags_' . $feed_id, $tags );
	}

	public function get_conditional_tags( $feed_id ) {
		$tags = get_option( '_gaddon_convertkit_custom_tags_' . $feed_id );
		return $tags;
	}

	public function get_tags(){
		$path = 'tags';
		$query_args = array();
		$request_body = null;
		$request_args = array();
		$response = ckgf_convertkit_api_request( $path, $query_args, $request_body, $request_args );
		if ( is_wp_error( $response ) ) {
			// ToDo: log error
			return array();
		} else {
			return $response['tags'];
		}
	}

	public function get_checkbox_fields( $fields ){
		$checkbox_fields = array();
		foreach ( $fields as $field ){
			if ( 'checkbox' == $field['type'] ){
				$checkbox_fields[] = $field;
			}
		}
		return $checkbox_fields;
	}


	/**
	 * Get ConvertKit Custom Fields from the API to be used for the dynamic field map
	 *
	 * @return array
	 */
	public function get_custom_fields() {

		$path = 'custom_fields';
		$query_args = array();
		$request_body = null;
		$request_args = array();
		$fields = array();

		$response = ckgf_convertkit_api_request( $path, $query_args, $request_body, $request_args );
		$custom_fields = $response['custom_fields'];

		if ( $custom_fields && ! is_wp_error( $custom_fields ) ) {

			$fields[] = array(
				'label'     => __( 'Choose a ConvertKit Field', 'convertkit' ),
			);

			foreach ( $custom_fields as $field ) {

				$fields[] = array(
					'value'     => $field['key'],
					'label'     => $field['label'],
				);
			}
		}

		return $fields;
	}


	/**
	 * Get ConvertKit Custom Fields from the API to be used for the dynamic field map
	 *
	 * @return array
	 */
	public function notused___get_conditional_tagsssss() {

		$path = 'tags';
		$query_args = array();
		$request_body = null;
		$request_args = array();
		$fields = array();

		$response = ckgf_convertkit_api_request( $path, $query_args, $request_body, $request_args );
		$custom_fields = $response['tags'];

		if ( $custom_fields && ! is_wp_error( $custom_fields ) ) {

			$fields[] = array(
				'label'     => __( 'Choose a ConvertKit Field', 'convertkit' ),
				);

			foreach ( $custom_fields as $field ) {

				$fields[] = array(
						'value'     => $field['key'],
						'label'     => $field['label'],
				);
			}
		}

		return $fields;
	}

	/**
	 * GF Settings callback
	 *
	 * Build a SELECT to be shown in Feed Settings to select ConvertKit form
	 * form data will be posted to.
	 *
	 * @param array $field
	 * @param bool|true $echo
	 *
	 * @return string
	 */
	public function settings_convertkit_form( $field, $echo = true ) {
		$forms = ckgf_convertkit_api_get_forms();

		ckgf_debug( $forms );

		if ( is_wp_error( $forms ) ) {
			$markup = sprintf( '%s: %s', __( 'Error', 'convertkit' ), $forms->get_error_message() );
		} elseif ( empty( $forms ) ) {
			$markup = sprintf( '%s: %s', __( 'Error', 'convertkit' ), __( 'Please configure some forms on ConvertKit', 'convertkit' ) );
		} else {
			$options = array(
				array(
					'label' => __( 'Select a ConvertKit form', 'convertkit' ),
					'value' => '',
				),
			);

			foreach ( $forms as $form ) {
				$options[] = array(
					'label' => esc_html( $form['name'] ),
					'value' => esc_attr( $form['id'] ),
				);
			}

			$markup = $this->settings_select(array_merge($field, array(
				'choices' => $options,
				'type'    => 'select',
			)), false);
		}

		if ( $echo ) {
			echo $markup;
		}

		return $markup;
	}

	/**
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feed_name' => __( 'Name' ),
			'form_id' => __( 'Form' ),
		);
	}

	/**
	 * @param $feed
	 *
	 * @return string|void
	 */
	public function get_column_value_form_id( $feed ) {
		$forms   = ckgf_convertkit_api_get_forms();
		$form_id = rgars( $feed, 'meta/form_id' );

		if ( is_array( $forms ) && isset( $forms[ $form_id ] ) ) {
			$form = $forms[ $form_id ];

			return sprintf( '<a href="%s" target="_blank">%s</a>', esc_attr( esc_url( $form['url'] ) ), esc_html( $forms[ $form_id ]['name'] ) );
		} else {
			return __( 'N/A' );
		}
	}

	/**
	 * Process the submitted Gravity Form
	 *
	 * Email and Name are required by the API. If custom fields are mapped to form fields
	 * they will be added to the API post.
	 *
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 * @return null
	 */
	public function process_feed( $feed, $entry, $form ) {

		$field_map_e = rgars( $feed, 'meta/field_map_e' );
		$field_map_n = rgars( $feed, 'meta/field_map_n' );
		$form_id     = rgars( $feed, 'meta/form_id' );

		$convertkit_custom_fields = $this->get_dynamic_field_map_fields( $feed, 'convertkit_custom_fields' );

		$fields = array();

		$custom_fields = $this->get_custom_fields();
		// do we have custom fields in the feed?  add them to fields
		foreach ( $custom_fields as $field ) {
			if ( isset( $field['value'] ) ) {
				if ( isset( $convertkit_custom_fields[ $field['value'] ] ) ) {
					$fields[ $field['value'] ] = $this->get_field_value( $form, $entry, $convertkit_custom_fields[ $field['value'] ] );
				}
			}
		}

		ckgf_convertkit_api_add_email( $form_id, $entry[ $field_map_e ], $entry[ $field_map_n ], null, $fields );
	}

	/**
	 * Get the current value of the CK API Key so it can be validated
	 *
	 * @return mixed
	 */
	public function get_api_key() {
		return $this->get_plugin_setting( 'api_key' );
	}

}
