<?php

namespace Staatic\Vendor;

/**
 * @var string $title Page heading.
 * @var string $heading Card heading.
 * @var string $message Plain-text guidance; never an exception message.
 * @var string $retryUrl Canonical, read-only GET URL.
 * @var string|null $secondaryUrl
 * @var string|null $secondaryLabel
 */
?>
<div class="wrap staatic-error-page">
    <h1><?php 
echo \esc_html($title);
?></h1>
    <div class="card">
        <h2><?php 
echo \esc_html($heading);
?></h2>
        <p><?php 
echo \esc_html($message);
?></p>
        <p class="staatic-error-page__actions">
            <a class="button button-secondary" href="<?php 
echo \esc_url($retryUrl);
?>"><?php 
echo \esc_html(\__('Try again', 'staatic'));
?></a>
            <?php 
if (!empty($secondaryUrl) && !empty($secondaryLabel)) {
    ?>
                <a href="<?php 
    echo \esc_url($secondaryUrl);
    ?>"><?php 
    echo \esc_html($secondaryLabel);
    ?></a>
            <?php 
}
?>
        </p>
    </div>
</div>
<?php 
