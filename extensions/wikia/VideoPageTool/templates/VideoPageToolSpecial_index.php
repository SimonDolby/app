<div class="WikiaGrid VPTForms">
	<div class="grid-2">
		<?= $app->renderView('LeftMenu',
			'Index',
			array('menuItems' => $leftMenuItems)
		) ?>
	</div>
	<div class="grid-4">
		<form>


		</form>



		<?//= $moduleView ?>
	</div>
	<div class="publish">
		<button class="big"><?= wfMessage( 'videopagetool-publish-button' ) ?></button>
	</div>
</div>

