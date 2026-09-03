<?php
$pageTitle = 'Health & Water';
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/settings_layout.php';

$currentBottle = (int) ($user['water_bottle_oz'] ?? 24);
$bottlePresets = [16, 24, 32];
$isBottleCustom = !in_array($currentBottle, $bottlePresets, true);

include __DIR__ . '/../../includes/header.php';
?>

<div class="profile-page settings-detail-page">
    <?php renderSettingsBackNav('Health & Water'); ?>
    <?php renderSettingsAlerts($error, $success); ?>

    <div class="settings-card settings-detail-card">
        <form method="POST" class="settings-detail-form">
            <input type="hidden" name="action" value="update_health">

            <div class="form-group">
                <label>Height</label>
                <div class="height-inputs">
                    <div class="input-with-unit">
                        <input type="number" name="height_feet" value="<?= $heightFeet ?>"
                               min="3" max="8" required>
                        <span class="input-unit">ft</span>
                    </div>
                    <div class="input-with-unit">
                        <input type="number" name="height_inches" value="<?= $heightInches ?>"
                               min="0" max="11" required>
                        <span class="input-unit">in</span>
                    </div>
                </div>
            </div>

            <?php if ($user['bmi'] ?? null): ?>
                <p class="form-hint">Current BMI: <strong><?= h($user['bmi']) ?></strong><?= ($user['daily_water_oz'] ?? null) ? ' · Daily water goal: ' . (int) $user['daily_water_oz'] . ' oz' : '' ?></p>
            <?php endif; ?>
            <p class="form-hint"><a href="/challenge/app/insight_weight.php">Update weight from Weight &amp; BMI Insights</a></p>

            <div class="form-group">
                <label>Water Bottle Size</label>
                <input type="hidden" id="water_bottle_oz" name="water_bottle_oz"
                       value="<?= $currentBottle ?>">
                <input type="hidden" id="bottle_mode" name="bottle_mode"
                       value="<?= $isBottleCustom ? 'custom' : 'preset' ?>">
                <div class="bottle-toggle" role="group" aria-label="Water bottle size">
                    <?php foreach ($bottlePresets as $preset): ?>
                        <button type="button"
                                class="bottle-toggle-btn <?= (!$isBottleCustom && $currentBottle === $preset) ? 'active' : '' ?>"
                                data-bottle-preset="<?= $preset ?>">
                            <?= $preset ?> oz
                        </button>
                    <?php endforeach; ?>
                    <button type="button"
                            class="bottle-toggle-btn <?= $isBottleCustom ? 'active' : '' ?>"
                            data-bottle-custom>
                        Custom
                    </button>
                </div>
                <div class="bottle-custom-wrap" <?= $isBottleCustom ? '' : 'hidden' ?>>
                    <div class="input-with-unit">
                        <input type="number" id="water_bottle_custom" name="water_bottle_custom"
                               value="<?= $isBottleCustom ? $currentBottle : '' ?>"
                               min="1" max="128" placeholder="e.g. 20">
                        <span class="input-unit">oz</span>
                    </div>
                </div>
                <small class="form-hint">Used for the "+Bottle" quick-add on your daily checklist.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="/challenge/assets/js/settings.js?v=1.0"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
