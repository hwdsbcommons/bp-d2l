
			<?php do_action( 'bp_before_member_' . bp_current_action() . '_content' ); ?>

			<?php // this is important! do not remove the classes in this DIV as AJAX relies on it! ?>
			<div id="groups-dir-list" class="dir-list groups courses <?php echo bp_current_action(); ?>">
				<?php bp_get_template_part( 'groups/groups-loop' ) ?>
			</div>

			<?php do_action( 'bp_after_member_' . bp_current_action() . '_content' ); ?>
