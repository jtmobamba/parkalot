<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>London Parking - TfL API Test</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1a2e;
            color: #eee;
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #00d4ff; margin-bottom: 10px; }
        .subtitle { color: #888; margin-bottom: 30px; }
        .api-info {
            background: #16213e;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #00d4ff;
        }
        .api-info a { color: #00d4ff; }
        .endpoints {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .endpoint {
            background: #0f3460;
            padding: 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .endpoint:hover { background: #1a4a7a; transform: translateY(-2px); }
        .endpoint h3 { color: #00d4ff; font-size: 14px; margin-bottom: 5px; }
        .endpoint code { color: #ffd700; font-size: 12px; }
        .endpoint p { color: #aaa; font-size: 12px; margin-top: 8px; }
        .results {
            background: #16213e;
            border-radius: 8px;
            padding: 20px;
        }
        .results h2 { color: #00d4ff; margin-bottom: 15px; }
        #loading { display: none; color: #ffd700; }
        #output {
            background: #0d1b2a;
            padding: 15px;
            border-radius: 8px;
            max-height: 600px;
            overflow: auto;
            font-family: monospace;
            font-size: 13px;
            white-space: pre-wrap;
        }
        .car-park-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        .car-park-card {
            background: #0f3460;
            border-radius: 8px;
            padding: 15px;
        }
        .car-park-card h4 { color: #fff; margin-bottom: 10px; }
        .spaces { display: flex; gap: 10px; margin-bottom: 10px; }
        .space-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .space-badge.free { background: #28a745; color: #fff; }
        .space-badge.total { background: #6c757d; color: #fff; }
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .status-available { background: #28a745; }
        .status-filling { background: #ffc107; color: #000; }
        .status-almost_full { background: #fd7e14; }
        .status-full { background: #dc3545; }
        .bay-types { margin-top: 10px; }
        .bay-type { font-size: 12px; color: #aaa; padding: 3px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>London Parking Availability</h1>
        <p class="subtitle">Real-time parking data powered by Transport for London API</p>

        <div class="api-info">
            <strong>Data Source:</strong> TfL Unified API |
            <a href="https://api.tfl.gov.uk/" target="_blank">Documentation</a> |
            <a href="https://api-portal.tfl.gov.uk/" target="_blank">Get API Key</a>
        </div>

        <div class="endpoints">
            <div class="endpoint" onclick="fetchEndpoint('/london-parking')">
                <h3>All Car Parks</h3>
                <code>GET /api/index.php?route=london-parking</code>
                <p>Get all London car parks with real-time availability</p>
            </div>
            <div class="endpoint" onclick="fetchEndpoint('/london-parking&id=CarParks_800491')">
                <h3>Single Car Park</h3>
                <code>GET /api/index.php?route=london-parking&id=...</code>
                <p>Get specific car park by ID</p>
            </div>
            <div class="endpoint" onclick="fetchEndpoint('/london-parking/search&q=Westminster')">
                <h3>Search by Location</h3>
                <code>GET /api/index.php?route=london-parking/search&q=...</code>
                <p>Search car parks by area name</p>
            </div>
            <div class="endpoint" onclick="fetchEndpoint('/london-parking/nearby&lat=51.5074&lon=-0.1278&radius=1000')">
                <h3>Nearby Car Parks</h3>
                <code>GET /api/index.php?route=london-parking/nearby&lat=...&lon=...</code>
                <p>Find car parks near coordinates</p>
            </div>
        </div>

        <div class="results">
            <h2>Results</h2>
            <div id="loading">Loading...</div>
            <div id="output">Click an endpoint above to test the API</div>
        </div>
    </div>

    <script>
        async function fetchEndpoint(route) {
            const loading = document.getElementById('loading');
            const output = document.getElementById('output');

            loading.style.display = 'block';
            output.innerHTML = '';

            try {
                const url = '../api/index.php?route=' + route;
                const response = await fetch(url);
                const data = await response.json();

                loading.style.display = 'none';

                if (data.carParks && Array.isArray(data.carParks)) {
                    output.innerHTML = renderCarParks(data);
                } else {
                    output.textContent = JSON.stringify(data, null, 2);
                }
            } catch (error) {
                loading.style.display = 'none';
                output.innerHTML = '<span style="color: #dc3545;">Error: ' + error.message + '</span>';
            }
        }

        function renderCarParks(data) {
            let html = `<div style="margin-bottom: 15px; color: #00d4ff;">
                Found ${data.count || 0} car parks
                ${data.source ? ' | Source: ' + data.source : ''}
            </div>`;

            if (data.carParks && data.carParks.length > 0) {
                html += '<div class="car-park-grid">';
                data.carParks.forEach(cp => {
                    html += `
                        <div class="car-park-card">
                            <h4>${cp.name}</h4>
                            <div class="spaces">
                                <span class="space-badge free">${cp.freeSpaces} Free</span>
                                <span class="space-badge total">${cp.totalSpaces} Total</span>
                            </div>
                            <span class="status-badge status-${cp.status}">${cp.status.replace('_', ' ')}</span>
                            <span style="margin-left: 10px; color: #aaa; font-size: 12px;">
                                ${cp.occupancyPercent}% occupied
                            </span>
                            ${cp.bayTypes ? '<div class="bay-types">' + cp.bayTypes.map(b =>
                                `<div class="bay-type">${b.type}: ${b.free}/${b.total} free</div>`
                            ).join('') + '</div>' : ''}
                        </div>
                    `;
                });
                html += '</div>';
            }

            return html;
        }

        // Auto-load all car parks on page load
        fetchEndpoint('/london-parking');
    </script>
</body>
</html>
