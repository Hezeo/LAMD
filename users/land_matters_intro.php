<?php
// land_matters_intro.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Land Matters</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet">
    
    <style>
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background-color: #ffffff;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Great Vibes', cursive;
        }

        .intro-container {
            position: relative;
            width: 80%;
            max-width: 800px;
            height: 150px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .text-svg {
            width: 100%;
            height: auto;
            overflow: visible;
        }

        .draw-text {
            font-family: 'Great Vibes', cursive;
            font-size: 100px;
            fill: transparent;
            stroke: #2c3e50;
            stroke-width: 1;
            stroke-linecap: round;
            stroke-linejoin: round;
            
            /* BULLETPROOF FIX: Hardcoding a large enough dash array so we don't need risky JS math */
            stroke-dasharray: 2000;
            stroke-dashoffset: 2000;
            
            animation: drawIn 3s cubic-bezier(0.65, 0, 0.35, 1) forwards;
        }

        .draw-text.fill-it {
            animation: fillText 0.8s ease forwards;
        }

        @keyframes drawIn {
            to { stroke-dashoffset: 0; }
        }

        @keyframes fillText {
            from { fill: transparent; stroke: #2c3e50; }
            to { fill: #2c3e50; stroke: transparent; }
        }

        .heart-pen {
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 10%;
            transform: translate(-50%, -50%) scale(0);
            opacity: 0;
            animation: moveHeart 3s cubic-bezier(0.65, 0, 0.35, 1) forwards, 
                       fadeHeart 0.3s ease forwards 2.8s;
            z-index: 10;
            filter: drop-shadow(0 0 8px rgba(214, 48, 49, 0.6));
        }

        @keyframes moveHeart {
            0%   { left: 10%; top: 50%; transform: translate(-50%, -50%) scale(0) rotate(-10deg); opacity: 1; }
            10%  { transform: translate(-50%, -50%) scale(1) rotate(-10deg); opacity: 1; }
            90%  { left: 90%; top: 50%; transform: translate(-50%, -50%) scale(1) rotate(10deg); opacity: 1; }
            100% { left: 92%; top: 50%; transform: translate(-50%, -50%) scale(0) rotate(10deg); opacity: 0; }
        }

        @keyframes fadeHeart {
            to { opacity: 0; transform: translate(-50%, -50%) scale(0); }
        }
    </style>
</head>
<body>

    <div class="intro-container">
        <svg class="heart-pen" viewBox="0 0 24 24" fill="#d63031">
            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
        </svg>

        <svg class="text-svg" viewBox="0 0 800 150">
            <text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" class="draw-text">
                Land Matters
            </text>
        </svg>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            // 1. FIRE THE REDIRECT FIRST. 
            // This ensures no matter what happens with the animation, you are never stuck.
            setTimeout(() => {
                window.location.href = 'user_login.php';
            }, 2900); // 4.5 seconds

            // 2. Safely apply the fill effect when drawing finishes
            const textElement = document.querySelector('.draw-text');
            if (textElement) {
                setTimeout(() => {
                    textElement.classList.add('fill-it');
                }, 3000);
            }
        });
    </script>
</body>
</html>