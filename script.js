// script.js
function showForm(formId) {
    event.preventDefault();
    
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    
    if (formId === 'register-form') {
        loginForm.classList.remove('active');
        registerForm.classList.add('active');
    } else {
        registerForm.classList.remove('active');
        loginForm.classList.add('active');
    }
}

function togglePassword(inputId, icon) {
    const input = document.getElementById(inputId);
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Auto-hide error messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const errorMessages = document.querySelectorAll('.error-message');
    errorMessages.forEach(function(message) {
        setTimeout(function() {
            message.style.opacity = '0';
            setTimeout(function() {
                message.remove();
            }, 300);
        }, 5000);
    });
});
// script.js
function showForm(formId) {
    event.preventDefault();
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    
    if (formId === 'register-form') {
        loginForm.classList.remove('active');
        registerForm.classList.add('active');
    } else {
        registerForm.classList.remove('active');
        loginForm.classList.add('active');
    }
}

function togglePassword(inputId, icon) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Three.js 3D Animation
const canvas = document.getElementById('canvas-3d');
const scene = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
const renderer = new THREE.WebGLRenderer({ canvas: canvas, alpha: true, antialias: true });

renderer.setSize(window.innerWidth, window.innerHeight);
camera.position.z = 5;

// Create multiple 3D objects
const geometry1 = new THREE.IcosahedronGeometry(0.8, 0);
const geometry2 = new THREE.TorusGeometry(0.6, 0.2, 16, 100);
const geometry3 = new THREE.OctahedronGeometry(0.7, 0);

const material1 = new THREE.MeshPhongMaterial({ 
    color: 0x667eea,
    wireframe: false,
    transparent: true,
    opacity: 0.7,
    emissive: 0x667eea,
    emissiveIntensity: 0.3
});

const material2 = new THREE.MeshPhongMaterial({ 
    color: 0x764ba2,
    wireframe: false,
    transparent: true,
    opacity: 0.7,
    emissive: 0x764ba2,
    emissiveIntensity: 0.3
});

const material3 = new THREE.MeshPhongMaterial({ 
    color: 0xf093fb,
    wireframe: false,
    transparent: true,
    opacity: 0.7,
    emissive: 0xf093fb,
    emissiveIntensity: 0.3
});

const mesh1 = new THREE.Mesh(geometry1, material1);
const mesh2 = new THREE.Mesh(geometry2, material2);
const mesh3 = new THREE.Mesh(geometry3, material3);

mesh1.position.set(-2, 1, 0);
mesh2.position.set(2, -1, -1);
mesh3.position.set(0, 2, -2);

scene.add(mesh1);
scene.add(mesh2);
scene.add(mesh3);

// Lighting
const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
scene.add(ambientLight);

const pointLight1 = new THREE.PointLight(0x667eea, 2, 100);
pointLight1.position.set(5, 5, 5);
scene.add(pointLight1);

const pointLight2 = new THREE.PointLight(0x764ba2, 2, 100);
pointLight2.position.set(-5, -5, 5);
scene.add(pointLight2);

// Animation variables
let time = 0;

// Animate
function animate() {
    requestAnimationFrame(animate);
    time += 0.01;

    // Bouncing and rotating animation
    mesh1.rotation.x += 0.01;
    mesh1.rotation.y += 0.01;
    mesh1.position.y = Math.sin(time) * 1.5 + 1;
    mesh1.position.x = Math.cos(time * 0.5) * 2 - 2;

    mesh2.rotation.x += 0.015;
    mesh2.rotation.z += 0.01;
    mesh2.position.y = Math.cos(time * 1.2) * 1.5 - 1;
    mesh2.position.x = Math.sin(time * 0.7) * 2 + 2;

    mesh3.rotation.y += 0.02;
    mesh3.rotation.z += 0.015;
    mesh3.position.y = Math.sin(time * 0.8) * 1.5 + 2;
    mesh3.position.x = Math.cos(time) * 1.5;

    // Lights animation
    pointLight1.position.x = Math.sin(time * 0.5) * 5;
    pointLight1.position.y = Math.cos(time * 0.5) * 5;
    
    pointLight2.position.x = Math.cos(time * 0.7) * 5;
    pointLight2.position.y = Math.sin(time * 0.7) * 5;

    renderer.render(scene, camera);
}

animate();

// Video background effect (particle system)
const videoContainer = document.getElementById('video-background');
const particlesCount = 100;

for (let i = 0; i < particlesCount; i++) {
    const particle = document.createElement('div');
    particle.style.position = 'absolute';
    particle.style.width = Math.random() * 3 + 1 + 'px';
    particle.style.height = particle.style.width;
    particle.style.background = `rgba(255, 255, 255, ${Math.random() * 0.5})`;
    particle.style.borderRadius = '50%';
    particle.style.left = Math.random() * 100 + '%';
    particle.style.top = Math.random() * 100 + '%';
    particle.style.animation = `float ${Math.random() * 10 + 10}s linear infinite`;
    particle.style.animationDelay = Math.random() * 5 + 's';
    videoContainer.appendChild(particle);
}

// Add floating animation
const style = document.createElement('style');
style.textContent = `
    @keyframes float {
        0%, 100% { transform: translate(0, 0); opacity: 0; }
        10% { opacity: 1; }
        90% { opacity: 1; }
        100% { transform: translate(${Math.random() * 200 - 100}px, -100vh); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Handle window resize
window.addEventListener('resize', () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
});


// Auto-hide error messages
document.addEventListener('DOMContentLoaded', function() {
    const errorMessages = document.querySelectorAll('.error-message');
    errorMessages.forEach(function(message) {
        setTimeout(function() {
            message.style.opacity = '0';
            setTimeout(function() {
                message.remove();
            }, 300);
        }, 5000);
    });
});