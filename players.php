<?php
$server_ip = "185.230.52.66";
$port = 30120;
$players_info_url = "http://{$server_ip}:{$port}/players.json";
$disconnected_players_url = "disconnected_players.json"; // Replace with the actual path to your disconnected_players.json

try {
    $connected_response = file_get_contents($players_info_url);
    $connected_players = json_decode($connected_response, true);
} catch (Exception $e) {
    $connected_players = [];
}

try {
    $disconnected_response = file_get_contents($disconnected_players_url);
    $disconnected_players = json_decode($disconnected_response, true);
} catch (Exception $e) {
    $disconnected_players = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3D Players Status</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            background-color: #000;
            font-family: Arial, sans-serif;
        }
        #scene {
            position: absolute;
            width: 100%;
            height: 100%;
            background: #000;
        }
        .player-dot {
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .player-label {
            position: absolute;
            color: #fff;
            background: rgba(0, 0, 0, 0.8);
            padding: 2px 4px;
            font-size: 12px;
            border-radius: 3px;
            white-space: nowrap;
        }
        .ping-icon, .disconnected-icon {
            width: 16px;
            height: 16px;
            vertical-align: middle;
        }
        #separator {
            position: absolute;
            left: 50%;
            width: 4px;
            height: 100%;
            background-color: #fff;
            transform: translateX(-50%);
        }
        .counter {
            position: absolute;
            top: 10px;
            color: #fff;
            font-size: 18px;
            font-weight: bold;
            background: rgba(0, 0, 0, 0.7);
            padding: 5px 10px;
            border-radius: 5px;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.8);
        }
        #online-counter {
            right: 10px;
        }
        #disconnected-counter {
            left: 10px;
        }
        canvas.line-canvas {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 1;
        }
        #central-dot-online, #central-dot-disconnected {
            position: absolute;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            z-index: 10;
        }
        #central-dot-online {
            background-color: #00FF00;
        }
        #central-dot-disconnected {
            background-color: #FF0000;
        }
    </style>
</head>
<body>
    <div id="scene"></div>
    <canvas id="lineCanvas" class="line-canvas"></canvas>
    <div id="separator"></div>
    <div id="online-counter" class="counter"></div>
    <div id="disconnected-counter" class="counter"></div>

    <!-- Central dots for online and disconnected players -->
    <div id="central-dot-online"></div>
    <div id="central-dot-disconnected"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/matter-js/0.17.1/matter.min.js"></script>
    <script>
        const Engine = Matter.Engine,
            Render = Matter.Render,
            World = Matter.World,
            Bodies = Matter.Bodies,
            Body = Matter.Body,
            Events = Matter.Events,
            engine = Engine.create();

        engine.world.gravity.y = 0;
        engine.world.gravity.x = 0;

        const scene = document.getElementById('scene');
        const render = Render.create({
            element: scene,
            engine: engine,
            options: {
                width: window.innerWidth,
                height: window.innerHeight,
                wireframes: false,
                background: '#000'
            }
        });

        const connectedPlayers = <?php echo json_encode($connected_players); ?>;
        const disconnectedPlayers = <?php echo json_encode($disconnected_players); ?>;
        const bodies = [];
        const halfWidth = window.innerWidth / 2;
        const margin = 20; // Margin to keep players within bounds

        let selectedDot = null;
        let offsetX = 0;
        let offsetY = 0;

        function addPlayerDots(players, isConnected) {
            players.forEach(player => {
                const color = isConnected ?
                    (player.ping > 80 ? '#FFA500' : '#00FF00') : 
                    '#FF0000';
                const initialX = isConnected ?
                    Math.random() * (window.innerWidth / 2 - 50) + window.innerWidth / 2 + 25 : 
                    Math.random() * (window.innerWidth / 2 - 50) + 25;

                const body = Bodies.circle(
                    initialX,
                    Math.random() * window.innerHeight,
                    10,
                    {
                        render: { fillStyle: color },
                        restitution: 0.8,
                        friction: 0.1,
                        frictionAir: 0.02
                    }
                );
                body.label = isConnected ? 'online' : 'disconnected';
                body.playerName = player.name;

                const el = document.createElement('div');
                el.classList.add('player-dot');
                el.style.backgroundColor = color;
                el.style.left = `${body.position.x}px`;
                el.style.top = `${body.position.y}px`;

                const label = document.createElement('div');
                label.classList.add('player-label');
                if (isConnected) {
                    const pingIcon = player.ping > 100 ?
                        'https://cdn.discordapp.com/emojis/1270800322180419645.webp?size=96&quality=lossless' :
                        player.ping > 80 ?
                        'https://cdn.discordapp.com/emojis/1270800334675120130.webp?size=96&quality=lossless' :
                        player.ping > 20 ?
                        'https://cdn.discordapp.com/emojis/1270800309593309245.webp?size=96&quality=lossless' :
                        '';
                    label.innerHTML = `${player.name} (${player.id}) <img class="ping-icon" src="${pingIcon}" /> ${player.ping} ms`;
                } else {
                    const disconnectedIcon = 'https://cdn.discordapp.com/emojis/1270803751002968156.webp?size=96&quality=lossless';
                    label.innerHTML = `${player.name} (${player.id}) <img class="disconnected-icon" src="${disconnectedIcon}" />`;
                }
                el.appendChild(label);

                el.addEventListener('click', () => {
                    const discordId = player.identifiers.find(id => id.startsWith('discord:')).split(':')[1];
                    window.open(`https://discord.com/users/${discordId}`);
                });

                el.addEventListener('mouseenter', () => {
                    Body.setStatic(body, true);
                });

                el.addEventListener('mouseleave', () => {
                    Body.setStatic(body, false);
                });

                el.addEventListener('mousedown', (event) => {
                    selectedDot = { body, element: el };
                    offsetX = event.clientX - body.position.x;
                    offsetY = event.clientY - body.position.y;

                    // Bring the selected dot to the front
                    el.style.zIndex = 1000;
                });

                document.addEventListener('mousemove', (event) => {
                    if (selectedDot) {
                        const x = event.clientX - offsetX;
                        const y = event.clientY - offsetY;

                        Body.setPosition(selectedDot.body, { x, y });
                        selectedDot.element.style.left = `${x}px`;
                        selectedDot.element.style.top = `${y}px`;

                        // Ensure the dot stays within bounds
                        if (selectedDot.body.label === 'online') {
                            if (x < halfWidth + margin) {
                                Body.setPosition(selectedDot.body, { x: halfWidth + margin, y });
                            }
                        } else {
                            if (x > halfWidth - margin) {
                                Body.setPosition(selectedDot.body, { x: halfWidth - margin, y });
                            }
                        }

                        if (y < margin) {
                            Body.setPosition(selectedDot.body, { x, y: margin });
                        } else if (y > window.innerHeight - margin) {
                            Body.setPosition(selectedDot.body, { x, y: window.innerHeight - margin });
                        }
                    }
                });

                document.addEventListener('mouseup', () => {
                    if (selectedDot) {
                        // Send the updated position to the server or handle as needed
                        selectedDot = null;
                    }
                });

                bodies.push(body);
                World.add(engine.world, body);
                scene.appendChild(el);

                Events.on(engine, 'afterUpdate', () => {
                    el.style.left = `${body.position.x}px`;
                    el.style.top = `${body.position.y}px`;

                    // Constrain players within the bounds of their designated area
                    if (body.label === 'online') {
                        if (body.position.x < halfWidth + margin) {
                            Body.setPosition(body, { x: halfWidth + margin, y: body.position.y });
                        }
                    } else {
                        if (body.position.x > halfWidth - margin) {
                            Body.setPosition(body, { x: halfWidth - margin, y: body.position.y });
                        }
                    }

                    // General boundary constraints
                    if (body.position.x < margin) {
                        Body.setPosition(body, { x: margin, y: body.position.y });
                    } else if (body.position.x > window.innerWidth - margin) {
                        Body.setPosition(body, { x: window.innerWidth - margin, y: body.position.y });
                    }

                    if (body.position.y < margin) {
                        Body.setPosition(body, { x: body.position.x, y: margin });
                    } else if (body.position.y > window.innerHeight - margin) {
                        Body.setPosition(body, { x: body.position.x, y: window.innerHeight - margin });
                    }

                    // Update the position of central dots
                    updateCentralDotsPositions();
                });

                if (body.label === 'online') {
                    setInterval(() => {
                        if (!body.isStatic) {
                            const forceMagnitude = 0.0005 * body.mass;
                            const angle = Math.random() * 2 * Math.PI;
                            const force = {
                                x: Math.cos(angle) * forceMagnitude,
                                y: Math.sin(angle) * forceMagnitude
                            };
                            Body.applyForce(body, body.position, force);
                        }
                    }, 100);
                }
            });
        }

        function drawConnectingLines() {
            const lineCanvas = document.getElementById('lineCanvas');
            const context = lineCanvas.getContext('2d');
            lineCanvas.width = window.innerWidth;
            lineCanvas.height = window.innerHeight;

            context.clearRect(0, 0, lineCanvas.width, lineCanvas.height);

            const centralDotOnline = document.getElementById('central-dot-online');
            const centralDotDisconnected = document.getElementById('central-dot-disconnected');
            const centralDotOnlinePos = {
                x: parseFloat(centralDotOnline.style.left) + 20,
                y: parseFloat(centralDotOnline.style.top) + 20
            };
            const centralDotDisconnectedPos = {
                x: parseFloat(centralDotDisconnected.style.left) + 20,
                y: parseFloat(centralDotDisconnected.style.top) + 20
            };

            bodies.forEach(body => {
                context.beginPath();
                context.moveTo(body.position.x, body.position.y);

                if (body.label === 'online') {
                    context.lineTo(centralDotOnlinePos.x, centralDotOnlinePos.y);
                } else {
                    context.lineTo(centralDotDisconnectedPos.x, centralDotDisconnectedPos.y);
                }

                context.strokeStyle = '#ffffff';
                context.stroke();
                context.closePath();
            });

            requestAnimationFrame(drawConnectingLines);
        }

        function updateCentralDotsPositions() {
            const centralDotOnline = document.getElementById('central-dot-online');
            const centralDotDisconnected = document.getElementById('central-dot-disconnected');

            centralDotOnline.style.left = `${window.innerWidth * 0.75 - 20}px`;
            centralDotOnline.style.top = `${window.innerHeight / 2 - 20}px`;

            centralDotDisconnected.style.left = `${window.innerWidth * 0.25 - 20}px`;
            centralDotDisconnected.style.top = `${window.innerHeight / 2 - 20}px`;
        }

        addPlayerDots(connectedPlayers, true);
        addPlayerDots(disconnectedPlayers, false);

        Render.run(render);
        Engine.run(engine);

        updateCentralDotsPositions();
        drawConnectingLines();

        window.addEventListener('resize', () => {
            render.canvas.width = window.innerWidth;
            render.canvas.height = window.innerHeight;
            updateCentralDotsPositions();
        });

        document.getElementById('online-counter').textContent = `Online: ${connectedPlayers.length}`;
        document.getElementById('disconnected-counter').textContent = `Disconnected: ${disconnectedPlayers.length}`;
    </script>
</body>
</html>
