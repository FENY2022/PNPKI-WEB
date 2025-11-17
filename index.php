<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Document Tracking & Management System (DDTMS) - DENR CARAGA</title>
    
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Use Inter font family -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7fafc; /* Light gray background */
        }
    </style>
    <link rel="icon" type="image/png" href="logo/icon.png">
</head>
<body class="text-gray-800">

    <!-- Header / Navigation -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex justify-between items-center">
            <!-- Logo/System Name -->
            <div class="flex items-center space-x-2">
                <svg class="w-8 h-8 text-blue-700" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 2a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 5a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-xl font-bold text-blue-800">DDTMS | DENR CARAGA</span>
            </div>
            
            <!-- Desktop Navigation -->
            <nav class="hidden md:flex space-x-6 text-sm font-medium">
                <a href="#workflow" class="text-gray-600 hover:text-blue-700 transition duration-150">Workflow</a>
                <a href="#features" class="text-gray-600 hover:text-blue-700 transition duration-150">Features</a>
                <a href="privacy.php" class="text-gray-600 hover:text-blue-700 transition duration-150">Privacy Policy</a>
            </nav>

            <!-- CTA Button -->
            <a href="login.php" class="hidden md:inline-flex px-4 py-2 text-sm font-semibold bg-blue-700 text-white rounded-lg hover:bg-blue-800 transition duration-150 shadow-md">
                Log In
            </a>
            
            <!-- Mobile Menu Button -->
            <button id="mobile-menu-button" class="md:hidden p-2 text-gray-600 hover:text-blue-700 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
            </button>
        </div>
        
        <!-- Mobile Menu (Hidden by default) -->
        <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-100">
            <a href="#workflow" class="block px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50">Workflow</a>
            <a href="#features" class="block px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50">Features</a>
            <a href="privacy.php" class="block px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50">Privacy Policy</a>
            <a href="login.php" class="block px-4 py-3 text-sm font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100">Log In</a>
        </div>
    </header>

    <!-- Hero Section -->
    <main>
        <div class="bg-gradient-to-r from-blue-700 to-blue-900 py-20 sm:py-32">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h1 class="text-4xl sm:text-6xl font-extrabold text-white leading-tight mb-4">
                    DENR CARAGA Digital Document Tracking & Management System (DDTMS)
                </h1>
                <p class="text-xl sm:text-2xl text-blue-200 mb-10 max-w-3xl mx-auto">
                    Streamlining Hierarchical Approvals, Digital Signing, and Archiving for DENR CARAGA Regional Documents.
                </p>
                <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                    <a href="#workflow" class="inline-flex justify-center items-center px-8 py-3 text-base font-medium text-blue-900 bg-white border border-transparent rounded-xl shadow-lg hover:bg-gray-100 transition duration-200 transform hover:scale-[1.02]">
                        Explore the Workflow
                    </a>
                    <a href="register.php" class="inline-flex justify-center items-center px-8 py-3 text-base font-medium text-white bg-transparent border-2 border-white rounded-xl shadow-lg hover:bg-white hover:text-blue-700 transition duration-200">
                        Request Access
                    </a>
                </div>
            </div>
        </div>

        <!-- Workflow Section -->
        <section id="workflow" class="py-16 sm:py-24 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl sm:text-4xl font-bold text-center text-gray-900 mb-4">
                    The Authoritative Document Approval Flow
                </h2>
                <p class="text-center text-lg text-gray-600 mb-12 max-w-3xl mx-auto">
                    Every document follows a strict, auditable path ensuring accountability and compliance at every stage, from drafting to final signature.
                </p>

                <!-- Workflow Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">

                    <!-- Stage 1: Drafting -->
                    <div class="relative bg-gray-50 p-6 rounded-xl shadow-lg border-t-4 border-blue-500 hover:shadow-xl transition duration-300 group">
                        <div class="flex items-center space-x-3 mb-4">
                            <span class="text-3xl font-extrabold text-blue-700">1</span>
                            <h3 class="text-xl font-semibold">Initiation & Drafting</h3>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">The document is created and the Section Chief enters the draft phase, allowing for initial edits and revisions.</p>
                        <span class="inline-block px-3 py-1 text-xs font-semibold text-white bg-blue-500 rounded-full">Section Chief</span>
                        <div class="absolute right-6 top-6 text-blue-300 group-hover:text-blue-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </div>
                        <!-- Connector Arrow (Desktop) -->
                        <span class="absolute right-[-24px] top-1/2 transform -translate-y-1/2 hidden lg:block text-blue-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin-round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                        </span>
                    </div>

                    <!-- Stage 2: Draft Review -->
                    <div class="relative bg-gray-50 p-6 rounded-xl shadow-lg border-t-4 border-teal-500 hover:shadow-xl transition duration-300 group">
                        <div class="flex items-center space-x-3 mb-4">
                            <span class="text-3xl font-extrabold text-teal-700">2</span>
                            <h3 class="text-xl font-semibold">Draft Review & Edit</h3>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">The Division Chief reviews, revises, provides feedback, and either sends the document back to SC or approves it for finalization.</p>
                        <span class="inline-block px-3 py-1 text-xs font-semibold text-white bg-teal-500 rounded-full">Division Chief</span>
                        <div class="absolute right-6 top-6 text-teal-300 group-hover:text-teal-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7l-6.5 6.5-3.5-3.5"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12s2-7 9-7 9 7 9 7-2 7-9 7-9-7-9-7z"></path></svg>
                        </div>
                        <!-- Connector Arrow (Desktop) -->
                        <span class="absolute right-[-24px] top-1/2 transform -translate-y-1/2 hidden lg:block text-teal-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                        </span>
                    </div>

                    <!-- Stage 3: Formal Signing -->
                    <div class="relative bg-gray-50 p-6 rounded-xl shadow-lg border-t-4 border-yellow-600 hover:shadow-xl transition duration-300 group">
                        <div class="flex items-center space-x-3 mb-4">
                            <span class="text-3xl font-extrabold text-yellow-700">3</span>
                            <h3 class="text-xl font-semibold">Formal Signing Chain</h3>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">The Section Chief finalizes and applies the first PNPKI signature, starting the critical sequence of DC $\to$ ARD $\to$ RED signatures.</p>
                        <span class="inline-block px-3 py-1 text-xs font-semibold text-white bg-yellow-600 rounded-full">SC, DC, ARD, RED</span>
                        <div class="absolute right-6 top-6 text-yellow-300 group-hover:text-yellow-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v3h8z"></path></svg>
                        </div>
                        <!-- Connector Arrow (Desktop) -->
                        <span class="absolute right-[-24px] top-1/2 transform -translate-y-1/2 hidden lg:block text-yellow-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                        </span>
                    </div>

                    <!-- Stage 4: High-Level Review & Return Loop -->
                    <div class="relative bg-gray-50 p-6 rounded-xl shadow-lg border-t-4 border-red-500 hover:shadow-xl transition duration-300 group">
                        <div class="flex items-center space-x-3 mb-4">
                            <span class="text-3xl font-extrabold text-red-700">4</span>
                            <h3 class="text-xl font-semibold">ARD/DC Correction Loop</h3>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">If the ARD rejects the document, it is immediately returned to the DC, who then sends it back to the SC for revision and re-signing.</p>
                        <span class="inline-block px-3 py-1 text-xs font-semibold text-white bg-red-500 rounded-full">ARD, DC, SC</span>
                        <div class="absolute right-6 top-6 text-red-300 group-hover:text-red-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.836 3.582a4.5 4.5 0 01-1.127.073v1.364l-2.296-2.296A1 1 0 0014.28 10h-2.12c-.528 0-1.04-.21-1.414-.585L8.5 7.293M14 4h7v5m0 0l-4 4m4-4l-4-4M3 10h3l1-3h4l-1 3h3l1-3h3"></path></svg>
                        </div>
                        <!-- Connector Arrow (Desktop) -->
                        <span class="absolute right-[-24px] top-1/2 transform -translate-y-1/2 hidden lg:block text-red-700">
                            <svg class="w-6 h-6 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                        </span>
                    </div>

                    <!-- Stage 5: Archiving -->
                    <div class="relative bg-gray-50 p-6 rounded-xl shadow-lg border-t-4 border-indigo-500 hover:shadow-xl transition duration-300 group">
                        <div class="flex items-center space-x-3 mb-4">
                            <span class="text-3xl font-extrabold text-indigo-700">5</span>
                            <h3 class="text-xl font-semibold">Records & Archiving</h3>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">Once the RED signs, the document is moved to the Records Office for official logging, archiving, and QR code generation for tracking.</p>
                        <span class="inline-block px-3 py-1 text-xs font-semibold text-white bg-indigo-500 rounded-full">Records Office</span>
                        <div class="absolute right-6 top-6 text-indigo-300 group-hover:text-indigo-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-16 sm:py-24 bg-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl sm:text-4xl font-bold text-center text-gray-900 mb-12">
                    Key System Capabilities
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    
                    <!-- Feature 1: PNPKI Integration -->
                    <div class="bg-white p-8 rounded-xl shadow-xl border-b-4 border-purple-600">
                        <svg class="w-10 h-10 text-purple-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.27a11.97 11.97 0 013.298 2.395A11.97 11.97 0 0112 20.25c-2.455 0-4.743-.873-6.505-2.395a12 12 0 013.298-2.395"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6.75A.75.75 0 014.5 6h15a.75.75 0 01.75.75v6.5a.75.75 0 01-.75.75H4.5a.75.75 0 01-.75-.75v-6.5z"></path></svg>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Secure Digital Signing (PNPKI)</h3>
                        <p class="text-gray-600">Integrate official government digital certificates for non-repudiable and legally binding document signatures across all signing stages.</p>
                    </div>

                    <!-- Feature 2: Audit Trail -->
                    <div class="bg-white p-8 rounded-xl shadow-xl border-b-4 border-orange-600">
                        <svg class="w-10 h-10 text-orange-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m4-1v4m-4-7h10"></path></svg>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Version Control & Audit Log</h3>
                        <p class="text-gray-600">Maintain a complete history of every version, edit, comment, and action taken, ensuring full transparency and accountability.</p>
                    </div>

                    <!-- Feature 3: Real-Time Tracking -->
                    <div class="bg-white p-8 rounded-xl shadow-xl border-b-4 border-green-600">
                        <svg class="w-10 h-10 text-green-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Real-Time Queue Management</h3>
                        <p class="text-gray-600">Users can instantly see which stage their document is in and who the current owner is, eliminating communication delays.</p>
                    </div>

                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-gray-800 text-white py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <div class="flex flex-col md:flex-row justify-between items-center border-b border-gray-700 pb-6 mb-6">
                    <div class="text-lg font-bold mb-4 md:mb-0">DDTMS: DENR CARAGA Document Tracker</div>
                    <div class="space-x-4">
                        <a href="#workflow" class="text-gray-400 hover:text-white transition duration-150">Workflow</a>
                        <a href="#features" class="text-gray-400 hover:text-white transition duration-150">Features</a>
                        <a href="privacy.php" class="text-gray-400 hover:text-white transition duration-150">Privacy Policy</a>
                    </div>
                </div>
                <p class="text-sm text-gray-400">&copy; 2025 DENR CARAGA DDTMS. All rights reserved. Built for streamlined public service.</p>
            </div>
        </footer>

    </main>

    <!-- JavaScript for Mobile Menu Toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const menuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');

            // Function to toggle the mobile menu visibility
            function toggleMenu() {
                const isHidden = mobileMenu.classList.contains('hidden');
                if (isHidden) {
                    mobileMenu.classList.remove('hidden');
                } else {
                    mobileMenu.classList.add('hidden');
                }
            }

            // Event listener for the button
            if (menuButton) {
                menuButton.addEventListener('click', toggleMenu);
            }

            // Close menu when a link is clicked (for better mobile UX)
            document.querySelectorAll('#mobile-menu a').forEach(link => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.add('hidden');
                });
            });
        });
    </script>
</body>
</html>