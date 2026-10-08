<?php

/*
|--------------------------------------------------------------------------
| Styled QR code design
|--------------------------------------------------------------------------
| Central place to tune the look of every generated QR (teacher + equipment).
| The encoded payload is NEVER affected by these — only the visuals. Keep the
| module/background contrast high and the logo ratio small so codes stay
| scannable. Error correction is forced to "H" so the centre logo is safe.
*/

return [
    // Colours
    'module_color' => '#2E7D32', // data dots (primary green)
    'eye_color'    => '#1B5E20', // the three corner finder eyes (darker accent)
    'background'   => '#FFFFFF', // ALWAYS a solid light background behind the code
    'frame_color'  => '#2E7D32', // outer rounded frame stroke
    'ring_color'   => '#2E7D32', // slim ring around the centre logo
    'label_bg'     => '#2E7D32', // name banner background
    'label_text'   => '#FFFFFF', // name banner text

    // Centre logo
    'logo_path'  => 'images/logo.png', // relative to public/
    'logo_ratio' => 0.45,              // fraction of the code width (0.20 = small, 0.45 = large; keep < 0.50 for reliable scanning)

    // Structure toggles
    'frame' => true, // draw the rounded outer frame
    'label' => true, // draw the name banner below the code

    // Geometry
    'quiet_zone'       => 4,   // light modules of padding around the code (standard)
    'error_correction' => 'H', // 30% — mandatory so the logo never breaks scanning

    // Raster output
    'png_size' => 1000, // minimum PNG width in px
];
