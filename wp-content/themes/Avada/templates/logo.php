<?php

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

if ( 'v7' === Avada()->settings->get( 'header_layout' ) ) {
	$logo_opening_markup = '<li class="fusion-middle-logo-menu-logo ';
	$logo_closing_markup = '</li>';
} else {
	$logo_opening_markup = '<div class="';
	$logo_closing_markup = '</div>';
}
?>

<?php echo $logo_opening_markup;?>fusion-logo" data-margin-top="<?php echo Avada()->settings->get( 'logo_margin', 'top' ); ?>" data-margin-bottom="<?php echo Avada()->settings->get( 'logo_margin', 'bottom' ); ?>" data-margin-left="<?php echo Avada()->settings->get( 'logo_margin', 'left' ); ?>" data-margin-right="<?php echo Avada()->settings->get( 'logo_margin', 'right' ); ?>">
	<?php
	/**
	 * The avada_logo_prepend hook.
	 */
	do_action( 'avada_logo_prepend' );
	?>
	<?php if ( ( Avada()->settings->get( 'logo', 'url' ) && '' !== Avada()->settings->get( 'logo', 'url' ) ) || ( Avada()->settings->get( 'logo_retina', 'url' ) && '' !== Avada()->settings->get( 'logo_retina', 'url' ) ) ) : ?>
		<a class="fusion-logo-link" href="<?php echo home_url( '/' ); ?>">
			<?php
			$logo_url = Avada_Sanitize::get_url_with_correct_scheme( Avada()->settings->get( 'logo', 'url' ) );

			// Use retina logo, if defqault one is not set.
			if ( '' === $logo_url ) {
				$logo_url = Avada_Sanitize::get_url_with_correct_scheme( Avada()->settings->get( 'logo_retina', 'url' ) );
				$logo_data = Avada()->images->get_logo_data( 'logo_retina' );
				$logo_data['style'] = '';
				if ( '' !== $logo_data['width'] ) {
					$logo_data['style'] = ' style="max-height:' . $logo_data['height'] . 'px;height:auto;"';
				}
			} else {
				$logo_data = Avada()->images->get_logo_data( 'logo' );
				$logo_data['style'] = '';
			}

			$logo_size['width']  = $logo_data['width'];
			$logo_size['height'] = $logo_data['height'];
			?>
			<img src="<?php echo $logo_url; ?>" width="<?php echo $logo_size['width']; ?>" height="<?php echo $logo_size['height']; ?>"<?php echo $logo_data['style']; ?> alt="<?php bloginfo( 'name' ); ?>" class="fusion-logo-1x fusion-standard-logo" />

			<?php if ( Avada()->settings->get( 'logo_retina', 'url' ) && '' !== Avada()->settings->get( 'logo_retina', 'url' ) ) : ?>
				<?php $retina_logo = Avada_Sanitize::get_url_with_correct_scheme( Avada()->settings->get( 'logo_retina', 'url' ) ); ?>
				<?php $style = 'style="max-height: ' . $logo_size['height'] . 'px; height: auto;"'; ?>
				<img src="<?php echo $retina_logo; ?>" width="<?php echo $logo_size['width']; ?>" height="<?php echo $logo_size['height']; ?>" alt="<?php bloginfo( 'name' ); ?>" <?php echo $style; ?> class="fusion-standard-logo fusion-logo-2x" />
			<?php else : ?>
				<img src="<?php echo $logo_url; ?>" width="<?php echo $logo_size['width']; ?>" height="<?php echo $logo_size['height']; ?>" alt="<?php bloginfo( 'name' ); ?>" class="fusion-standard-logo fusion-logo-2x" />
			<?php endif; ?>

			<!-- mobile logo -->
			<?php if ( Avada()->settings->get( 'mobile_logo', 'url' ) && '' !== Avada()->settings->get( 'mobile_logo', 'url' ) ) : ?>
				<?php $mobile_logo_data = Avada()->images->get_logo_data( 'mobile_logo' ); ?>
				<img src="<?php echo $mobile_logo_data['url']; ?>" width="<?php echo $mobile_logo_data['width']; ?>" height="<?php echo $mobile_logo_data['height']; ?>" alt="<?php bloginfo( 'name' ); ?>" class="fusion-logo-1x fusion-mobile-logo-1x" />

				<?php if ( Avada()->settings->get( 'mobile_logo_retina', 'url' ) && '' != Avada()->settings->get( 'mobile_logo_retina', 'url' ) ) : ?>
					<?php
					$retina_mobile_logo_data = Avada()->images->get_logo_data( 'mobile_logo_retina' );
					$style = 'style="max-height: ' . $retina_mobile_logo_data['height'] . 'px; height: auto;"';
					?>
					<img src="<?php echo $retina_mobile_logo_data['url']; ?>" width="<?php echo $retina_mobile_logo_data['width']; ?>" height="<?php echo $retina_mobile_logo_data['height']; ?>" alt="<?php bloginfo( 'name' ); ?>" <?php echo $style; ?> class="fusion-logo-2x fusion-mobile-logo-2x" />
				<?php else : ?>
					<img src="<?php echo $mobile_logo_data['url']; ?>" width="<?php echo $mobile_logo_data['width']; ?>" height="<?php echo $mobile_logo_data['height']; ?>" alt="<?php bloginfo( 'name' ); ?>" class="fusion-logo-2x fusion-mobile-logo-2x" />
				<?php endif; ?>
			<?php endif; ?>

			<!-- sticky header logo -->
			<?php if ( Avada()->settings->get( 'sticky_header_logo', 'url' ) && '' !== Avada()->settings->get( 'sticky_header_logo', 'url' ) && ( in_array( Avada()->settings->get( 'header_layout' ), array( 'v1', 'v2', 'v3', 'v6', 'v7' ) ) || ( ( in_array( Avada()->settings->get( 'header_layout' ), array( 'v4', 'v5' ) ) && 'menu_and_logo' === Avada()->settings->get( 'header_sticky_type2_layout' ) ) ) ) ) : ?>
				<?php $sticky_logo_data = Avada()->images->get_logo_data( 'sticky_header_logo' ); ?>
				<img src="<?php echo $sticky_logo_data['url']; ?>" width="<?php echo $sticky_logo_data['width']; ?>" height="<?php echo $sticky_logo_data['height']; ?>" alt="<?php bloginfo( 'name' ); ?>" class="fusion-logo-1x fusion-sticky-logo-1x" />

				<?php if ( Avada()->settings->get( 'sticky_header_logo_retina', 'url' ) && '' !== Avada()->settings->get( 'sticky_header_logo_retina', 'url' ) ) : ?>
					<?php
					$retina_sticky_logo_data = Avada()->images->get_logo_data( 'sticky_header_logo_retina' );
					$style = 'style="max-height: ' . $retina_sticky_logo_data['height'] . 'px; height: auto;"';
					?>
					<img src="<?php echo $retina_sticky_logo_data['url']; ?>" width="<?php echo $retina_sticky_logo_data['width']; ?>" height="<?php echo $retina_sticky_logo_data['height']; ?>" alt="<?php bloginfo( 'name' ); ?>" <?php echo $style; ?> class="fusion-logo-2x fusion-sticky-logo-2x" />
				<?php else : ?>
					<img src="<?php echo $sticky_logo_data['url']; ?>" width="<?php echo $sticky_logo_data['width']; ?>" height="<?php echo $sticky_logo_data['height']; ?>" alt="<?php bloginfo( 'name' ); ?>" class="fusion-logo-2x fusion-sticky-logo-2x" />
				<?php endif; ?>
			<?php endif; ?>
		</a>
	<?php endif; ?>
	<?php
	/**
	 * The avada_logo_append hook.
	 *
	 * @hooked avada_header_content_3 - 10.
	 */
	do_action( 'avada_logo_append' );

	?>
<?php echo $logo_closing_markup; ?>
