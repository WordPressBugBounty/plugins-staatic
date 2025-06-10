<?php

namespace Staatic\Vendor;

/**
 * @var Staatic\WordPress\Setting\Build\PreviewUrlSetting $setting
 * @var array $attributes
 */
?>

<fieldset>
    <legend class="screen-reader-text"><span><?php 
echo \esc_html($setting->label());
?></span></legend>
    <div
        data-staatic-component="DestinationUrl"
        data-name="<?php 
echo \esc_attr($setting->name());
?>"
        data-hide-offline-url="true"
    ></div>
    <input
        type="text"
        class="regular-text code"
        name="<?php 
echo \esc_attr($setting->name());
?>"
        id="<?php 
echo \esc_attr($setting->name());
?>"
        value="<?php 
echo \esc_attr($setting->value());
?>"
        <?php 
echo $attributes['disabled'] ?? \false ? 'disabled="disabled"' : '';
?>
    >
    <?php 
if (isset($attributes['locked'])) {
    ?>
        <span
            id="<?php 
    echo \esc_attr($setting->name());
    ?>_locked"
            class="staatic-adornment dashicons dashicons-lock"
            title="<?php 
    echo \esc_attr($attributes['locked']);
    ?>"
        ></span>
    <?php 
}
?>
    <?php 
if ($setting->description()) {
    ?>
        <p class="description">
            <?php 
    echo \wp_kses_post($setting->description());
    ?>
        </p>
    <?php 
}
?>
</fieldset>
<?php 
