/* =====================
   Greetings Typewriter
===================== */
function typeWriter(text, element, delay = 100, callback = null) {
  let i = 0;
  function typing() {
    if (i < text.length) {
      element.textContent += text.charAt(i);
      i++;
      setTimeout(typing, delay);
    } else {
      if (callback) {
        callback();
      }
    }
  }
  typing();
}

function setGreeting() {
  const greetingElement = document.getElementById('greeting');
  const now = new Date();
  const hour = now.getHours();
  let greetingText = '';

  // 1. Determine the Greeting
  if (hour >= 5 && hour < 12) {
    greetingText = 'Good Morning!';
  } else if (hour >= 12 && hour < 17) {
    greetingText = 'Good Afternoon!';
  } else {
    greetingText = 'Good Evening!';
  }

  // 2. Clear previous content
  greetingElement.innerHTML = '';

  // 3. Animate "Good Morning!" FIRST
  typeWriter(greetingText, greetingElement, 100, function() {
    
    // 4. Once "Good Morning" is done, add the space
    greetingElement.innerHTML += '&nbsp;';
    
    // 5. Create the cursive span for "Welcome to"
    const welcomeSpan = document.createElement('span');
    welcomeSpan.className = 'cursive-welcome';
    greetingElement.appendChild(welcomeSpan);

    // 6. Animate "Welcome to" LAST
    typeWriter('Welcome to', welcomeSpan, 120);
  });
}

window.onload = setGreeting;

//LOGIN VALIDATION
document.getElementById('username').focus();
document.getElementById('username').addEventListener('input', function () {
  const errorDiv = document.querySelector('.error-message');
  if (errorDiv) errorDiv.style.display = 'none';
});

document.getElementById('password').addEventListener('input', function () {
  const errorDiv = document.querySelector('.error-message');
  if (errorDiv) errorDiv.style.display = 'none';
});