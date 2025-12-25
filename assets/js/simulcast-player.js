jQuery(document).ready(function ($) {
    const API_URL = simulcastData.apiUrl;
    let player = null;

    async function checkStream() {
        try {
            const response = await fetch(API_URL);
            const data = await response.json();

            const statusDiv = document.getElementById('simulcast-status');

            if (data.isLive) {
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
                        // First load
                        if (player.src() !== data.hlsUrl) {
                            player.src({ type: 'application/x-mpegURL', src: data.hlsUrl });
                            // Attempt to play, but catch errors (autoplay policies)
                            player.play().catch(e => {
                                if (e.name === 'NotAllowedError') {
                                    console.log('Autoplay blocked (browser policy).');
                                } else {
                                    console.log('Playback failed (stream likely not ready), retrying...');
                                }
                            });
                        }
                    });
                } else if (!player.isDisposed()) {
                    // Update source if changed OR if player is in error state (retry logic)
                    if (player.src() !== data.hlsUrl || player.error()) {
                        console.log('Stream check: updating source or retrying...');
                        if (player.error()) {
                            // Re-enter startup mode
                            player.addClass('vjs-live-startup');
                            player.error(null); // Clear error overlay
                            player.removeClass('vjs-waiting'); // Reset spinner state for fresh attempt
                        }
                        player.src({ type: 'application/x-mpegURL', src: data.hlsUrl });
                        player.play().catch(e => {
                            if (e.name !== 'NotAllowedError') console.log('Retry playback failed, will try again...');
                        });
                    }
                }

            } else {
                statusDiv.textContent = '⚫ OFFLINE';
                statusDiv.className = 'simulcast-status offline';

                $('.video-container').hide();
                $('#simulcast-video').hide();

                if (player && !player.isDisposed()) {
                    player.pause();
                }
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
});
