# Simulcast.me Live Stream Plugin

A WordPress plugin to easily embed your self-hosted **Simulcast.me** livestream using the API and HLS player.

## Features

- **Live Status Indicator**: Automatically polls your stream status every 5 seconds.
- **Auto-Play**: Automatically shows and loads the player when you go live.
- **HLS Support**: Uses Video.js for reliable HLS playback.
- **Secure**: Proxies API requests through your WordPress backend to keep your API Key hidden.
- **Customizable**: Simple shortcode to place the player anywhere.

## Installation

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Keep the file structure intact.

## Configuration

1. Go to **Settings** > **Simulcast.me Stream**.
2. Enter your **Simulcast Public API Key**.
3. Use the "Show/Hide" button to verify your key.
4. Click **Save Changes**.

## Usage

Embed the player on any page or post using the shortcode:

```
[simulcast_player]
```

## Troubleshooting

- **CORS Errors**: This plugin uses a backend proxy to avoid CORS issues. Ensure your WordPress Rest API is enabled (`/wp-json/`).
- **Stream Offline**: Double check your API Key and ensure your stream is actually live on the Simulcast.me dashboard.
