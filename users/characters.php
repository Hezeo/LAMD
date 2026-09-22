<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSS Pineapple Art - Animated</title>
    <style>
        body {
            margin: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f4f4f4;
            overflow: hidden;
            font-family: sans-serif;
        }

        /* Main Container - Applies the Bounce Animation */
        .pineapple-container {
            position: relative;
            width: 300px;
            height: 450px;
            animation: bounce 1.2s infinite ease-in-out;
        }

        /* --- ANIMATIONS DEFINITION --- */
        
        /* 1. Bounce Animation for the whole body */
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        /* 2. Wave Animation for the Left Arm */
        @keyframes wave {
            0%, 100% { transform: rotate(-20deg); }
            50% { transform: rotate(-50deg); } /* Waves up */
        }

        /* 3. Blink Animation for Eyes */
        @keyframes blink {
            0%, 90%, 100% { transform: scaleY(1); }
            95% { transform: scaleY(0.1); } /* Squint shut */
        }

        /* 4. Sway Animation for Leaves */
        @keyframes sway {
            0%, 100% { transform: translateX(-50%) rotate(var(--r)); }
            50% { transform: translateX(-50%) rotate(calc(var(--r) - 5deg)); }
        }


        /* --- BODY STYLES --- */

        .body {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 240px;
            height: 320px;
            background-color: #f1c40f;
            border-radius: 45% 45% 50% 50%;
            box-shadow: inset -20px -20px 40px rgba(0,0,0,0.05), inset 10px 10px 20px rgba(255,255,255,0.2);
            overflow: hidden;
            z-index: 1;
        }

        .texture {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: 
                radial-gradient(circle at center, #d35400 2px, transparent 2.5px),
                linear-gradient(45deg, transparent 45%, #e67e22 45%, #e67e22 55%, transparent 55%);
            background-size: 25px 25px;
            opacity: 0.3;
            border-radius: inherit;
        }

        /* --- LEAVES --- */
        .leaves-container {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
            height: 160px;
            z-index: 2;
        }

        .leaf {
            position: absolute;
            bottom: 0;
            left: 50%;
            background: linear-gradient(to top, #27ae60, #2ecc71);
            border-radius: 50% 50% 0 0;
            transform-origin: bottom center;
            /* Apply Sway Animation */
            animation: sway 2s infinite ease-in-out;
        }

        /* Specific Positions and Animation Delays for Leaves */
        .leaf:nth-child(1) { width: 30px; height: 100px; --r: 0deg; z-index: 5; animation-delay: 0s; }
        .leaf:nth-child(2) { width: 25px; height: 90px; --r: -25deg; left: 45%; z-index: 4; animation-delay: 0.1s; }
        .leaf:nth-child(3) { width: 25px; height: 90px; --r: 25deg; left: 55%; z-index: 4; animation-delay: 0.2s; }
        .leaf:nth-child(4) { width: 20px; height: 70px; --r: -45deg; left: 35%; z-index: 3; animation-delay: 0.3s; }
        .leaf:nth-child(5) { width: 20px; height: 70px; --r: 45deg; left: 65%; z-index: 3; animation-delay: 0.4s; }
        .leaf:nth-child(6) { width: 18px; height: 55px; --r: -60deg; left: 25%; z-index: 2; animation-delay: 0.5s; }
        .leaf:nth-child(7) { width: 18px; height: 55px; --r: 60deg; left: 75%; z-index: 2; animation-delay: 0.6s; }

        /* --- FACE --- */
        .face {
            position: absolute;
            top: 100px;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
            height: 100px;
            z-index: 10;
        }

        .eye {
            position: absolute;
            width: 15px;
            height: 20px;
            background-color: #2c3e50;
            border-radius: 50%;
            top: 20px;
            /* Apply Blink Animation */
            animation: blink 3s infinite;
        }
        .eye.left { left: 50px; animation-delay: 0s; }
        .eye.right { right: 50px; animation-delay: 0.1s; } /* Slight delay for realism */

        .eye::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 6px;
            height: 6px;
            background: white;
            border-radius: 50%;
        }

        .cheek {
            position: absolute;
            width: 30px;
            height: 15px;
            background-color: #e74c3c;
            opacity: 0.3;
            border-radius: 50%;
            top: 50px;
        }
        .cheek.left { left: 25px; }
        .cheek.right { right: 25px; }

        .mouth {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 30px;
            border-bottom: 5px solid #2c3e50;
            border-radius: 0 0 50% 50%;
        }

        /* --- ARMS --- */
        .arm {
            position: absolute;
            top: 300px;
            width: 80px;
            height: 25px;
            background-color: #f1c40f;
            border-radius: 20px;
            z-index: 0;
        }

        /* Left Arm - WAVING */
        .arm.left {
            left: -20px;
            border-radius: 20px 0 0 20px;
            transform-origin: right center; /* Pivot from the body */
            animation: wave 0.8s infinite ease-in-out;
        }
        
        /* Right Arm - Static slight move to match bounce */
        .arm.right {
            right: -20px;
            border-radius: 0 20px 20px 0;
            transform: rotate(20deg);
        }

        .arm::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: linear-gradient(45deg, transparent 45%, rgba(211, 84, 0, 0.2) 45%, rgba(211, 84, 0, 0.2) 55%, transparent 55%);
            background-size: 10px 10px;
            border-radius: inherit;
        }
    </style>
</head>
<body>

    <div class="pineapple-container">
        <!-- Leaves -->
        <div class="leaves-container">
            <div class="leaf"></div>
            <div class="leaf"></div>
            <div class="leaf"></div>
            <div class="leaf"></div>
            <div class="leaf"></div>
            <div class="leaf"></div>
            <div class="leaf"></div>
        </div>

        <!-- Arms -->
        <div class="arm left"></div>
        <div class="arm right"></div>

        <!-- Main Body -->
        <div class="body">
            <div class="texture"></div>
            
            <!-- Face -->
            <div class="face">
                <div class="eye left"></div>
                <div class="eye right"></div>
                <div class="cheek left"></div>
                <div class="cheek right"></div>
                <div class="mouth"></div>
            </div>
        </div>
    </div>

</body>
</html>