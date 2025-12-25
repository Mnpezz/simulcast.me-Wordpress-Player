jQuery(document).ready(function ($) {
    const API_URL = simulcastData.apiUrl;
    let player = null;

    // Initial state tracking
    let wasLive = false;

    async function checkStream() {
        try {
            const response = await fetch(API_URL);
            const data = await response.json();

            const statusDiv = document.getElementById('simulcast-status');

            if (data.isLive) {
                // Determine if this is a "Fresh Start" (transition from Offline -> Live)
                const isFreshStart = !wasLive;

                // Add timestamp to force fresh manifest (Cache Busting)
                // If URL already has params, use &, otherwise ?
                const separator = data.hlsUrl.includes('?') ? '&' : '?';
                const hlsUrlWithTime = `${data.hlsUrl}${separator}t=${new Date().getTime()}`;

                statusDiv.textContent = `🔴 LIVE: ${data.streamKeyLabel}`;
                statusDiv.className = 'simulcast-status live';

                // Show container
                $('.video-container').show();
                $('#simulcast-video').show();

                // Initialize player if not already done
                if (!player) {
                    player = videojs('simulcast-video', {
                        responsive: true,
                        muted: true, // Auto-mute to allow autoplay
                        autoplay: 'muted',
                        html5: {
                            vjs: {
                                overrideNative: true
                            }
                        }
                    });

                    // Add startup class to hide initial errors
                    player.addClass('vjs-live-startup');

                    // When playback actually starts, remove the startup class
                    player.on('playing', () => {
                        player.removeClass('vjs-live-startup');
                        player.removeClass('vjs-waiting');
                    });

                    // Hide the error display for 404/Source errors (common during startup)
                    player.on('error', () => {
                        const err = player.error();
                        if (err && err.code === 4) {
                            // Ensure we are in startup mode so CSS hides the error
                            player.addClass('vjs-live-startup');
                            // Show the spinner so user knows we are trying
                            player.addClass('vjs-waiting');
                        }
                    });

                    player.ready(() => {
                        // First load - Always load with fresh timestamp
                        player.src({ type: 'application/x-mpegURL', src: hlsUrlWithTime });
                        // Attempt to play, but catch errors (autoplay policies)
                        player.play().catch(e => {
                            if (e.name === 'NotAllowedError') {
                                console.log('Autoplay blocked (browser policy).');
                            } else {
                                console.log('Playback failed (stream likely not ready), retrying...');
                            }
                        });
                    });
                } else if (!player.isDisposed()) {
                    // Update source if changed OR if player is in error state (retry logic)
                    // OR if this is a specific Fresh Start (stream just came back online)

                    // Note regarding Fresh Start: Even if the base URL (data.hlsUrl) is the same,
                    // we MUST reload if we just came online to clear old buffer/cache.
                    const currentSrc = player.currentSrc();
                    // Check if base URL matches (ignoring timestamp)
                    const isSameBaseUrl = currentSrc && currentSrc.includes(data.hlsUrl);

                    if (!isSameBaseUrl || player.error() || isFreshStart) {
                        console.log('Stream check: updating source (Fresh Start or Retry)...');

                        if (player.error()) {
                            // Re-enter startup mode
                            player.addClass('vjs-live-startup');
                            player.error(null); // Clear error overlay
                            player.removeClass('vjs-waiting'); // Reset spinner state for fresh attempt
                        }

                        player.src({ type: 'application/x-mpegURL', src: hlsUrlWithTime });
                        player.play().catch(e => {
                            if (e.name !== 'NotAllowedError') console.log('Retry playback failed, will try again...');
                        });
                    }
                }

                wasLive = true;

            } else {
                statusDiv.textContent = '⚫ OFFLINE';
                statusDiv.className = 'simulcast-status offline';

                $('.video-container').hide();
                $('#simulcast-video').hide();

                if (player && !player.isDisposed()) {
                    player.pause();
                }

                wasLive = false;
            }
        } catch (error) {
            console.error('Simulcast Check Error:', error);
            $('#simulcast-status').text('Error checking stream status.');
        }
    }

    // Initial check
    checkStream();

    // Poll every 2.5 seconds (responsive retry)
    setInterval(checkStream, 2500);

    /* --- Tipping Logic --- */
    const $modal = $('#simulcast-tip-modal');
    const $customInput = $('#simulcast-custom-tip');
    let selectedAmount = 0;

    // Open Modal
    $('#open-tip-modal').on('click', function () {
        $modal.fadeIn(200);
        $modal.css('display', 'flex'); // Ensure flex for centering
    });

    // Close Modal
    $('#close-tip-modal, .simulcast-modal').on('click', function (e) {
        if (e.target === this) { // Click on backdrop or X
            $modal.fadeOut(200);
            $('#simulcast-tip-error').hide();
        }
    });

    // Content click shouldn't close
    $('.simulcast-modal-content').on('click', function (e) {
        e.stopPropagation();
    });

    // Preset Selection
    $('.simulcast-tip-preset').on('click', function () {
        $('.simulcast-tip-preset').removeClass('selected');
        $(this).addClass('selected');
        selectedAmount = $(this).data('amount');
        $customInput.val(''); // Clear custom
    });

    // Custom Input Logic
    $customInput.on('input', function () {
        $('.simulcast-tip-preset').removeClass('selected');
        selectedAmount = parseFloat($(this).val());
    });

    // Submit Tip
    $('#simulcast-submit-tip').on('click', function () {
        const checkoutUrl = simulcastData.checkoutUrl;
        const productId = simulcastData.tipProductId;
        const errorDiv = $('#simulcast-tip-error');

        // Validation
        if (!selectedAmount || selectedAmount < 1) {
            errorDiv.text('Please enter a valid amount (minimum $1).').show();
            return;
        }

        if (!checkoutUrl || !productId) {
            errorDiv.text('Error: Checkout not configured properly.').show();
            return;
        }

        // Create a temporary form to submit to new tab
        const form = $('<form>', {
            'action': checkoutUrl,
            'method': 'POST',
            'target': '_blank' // Open in new tab so stream keeps playing
        });

        // Add Product ID (Trigger Add-To-Cart)
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'add-to-cart',
            'value': productId
        }));

        // Add Custom Tip Amount
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'simulcast_tip_amount',
            'value': selectedAmount
        }));

        // Submit
        $('body').append(form);
        form.submit();

        // Cleanup Modal
        $modal.fadeOut();
        form.remove();

        // Optional: Show "Thanks" message?
        // alert('Thank you! Please complete your tip in the new tab.');
    });
});
