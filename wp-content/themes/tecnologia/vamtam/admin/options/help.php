<?php
return array(
	'name' => esc_html__( 'Help', 'tecnologia' ),
	'auto' => true,
	'config' => array(

		array(
			'name' => esc_html__( 'Help', 'tecnologia' ),
			'type' => 'title',
			'desc' => '',
		),

		array(
			'name' => esc_html__( 'Help', 'tecnologia' ),
			'type' => 'start',
			'nosave' => true,
		),
//----
		array(
			'type' => 'docs',
		),

			array(
				'type' => 'end',
			),
	),
);
