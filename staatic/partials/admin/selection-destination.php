<?php

namespace Staatic\Vendor;

$publishLiveLabel = \__('Publish Selection to Live Site', 'staatic');
$publishPreviewLabel = \__('Publish Selection to Preview Site', 'staatic');
$buildLiveLabel = \__('Build Selection for Live Site', 'staatic');
$buildPreviewLabel = \__('Build Selection for Preview Site', 'staatic');
$submitLabel = $deploy ? $isPreview ? $publishPreviewLabel : $publishLiveLabel : ($isPreview ? $buildPreviewLabel : $buildLiveLabel);
if (!empty($reviewDestination)) {
    ?>
    <div class="notice notice-info inline"><p><?php 
    \_e('Destination updated. Review your selection before publishing.', 'staatic');
    ?></p></div>
<?php 
}
?>
<div id="staatic-selection-destination"
    data-publish-live="<?php 
echo \esc_attr($publishLiveLabel);
?>"
    data-publish-preview="<?php 
echo \esc_attr($publishPreviewLabel);
?>"
    data-build-live="<?php 
echo \esc_attr($buildLiveLabel);
?>"
    data-build-preview="<?php 
echo \esc_attr($buildPreviewLabel);
?>"
    data-merge-live="<?php 
\esc_attr_e('Merge with Active Live Publication', 'staatic');
?>"
    data-merge-preview="<?php 
\esc_attr_e('Merge with Active Preview Publication', 'staatic');
?>"
    data-live-merge-available="<?php 
echo !empty($liveMergeAvailable) ? '1' : '0';
?>"
    data-preview-merge-available="<?php 
echo !empty($previewMergeAvailable) ? '1' : '0';
?>"
>
    <input type="hidden" name="rendered_preview" value="<?php 
echo $isPreview ? '1' : '0';
?>">
    <?php 
if ($supportsPreview) {
    ?>
        <h2><?php 
    \_e('Destination', 'staatic');
    ?></h2>
        <fieldset>
            <legend class="screen-reader-text"><?php 
    \_e('Destination', 'staatic');
    ?></legend>
            <div class="staatic-selection-destinations">
                <label><input type="radio" name="preview" value="0" <?php 
    echo !$isPreview ? 'checked="checked"' : '';
    ?>> <?php 
    \_e('Live Site', 'staatic');
    ?></label>
                <label><input type="radio" name="preview" value="1" <?php 
    echo $isPreview ? 'checked="checked"' : '';
    ?>> <?php 
    \_e('Preview Site', 'staatic');
    ?></label>
            </div>
            <p><button type="submit" class="button staatic-selection-refresh" name="update_destination" value="1"><?php 
    \_e('Update Destination', 'staatic');
    ?></button></p>
        </fieldset>
    <?php 
} else {
    ?>
        <input type="hidden" name="preview" value="0">
    <?php 
}
?>
</div>
<?php 
