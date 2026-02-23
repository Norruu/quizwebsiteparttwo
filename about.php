<?php
/**
 * About Us Page
 * Company information, team, and contact details.
 */
require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen py-10 px-4 bg-gray-100">
    <div class="max-w-4xl mx-auto">

        <!-- Hero -->
        <div class="text-center mb-10">
            <h1 class="font-game text-4xl gradient-text mb-3">🎮 About Us</h1>
            <p class="text-gray-500 text-lg">Get to know the team behind Bountiful Harvest!</p>
        </div>

        <!-- About Section -->
        <div class="bg-white rounded-2xl shadow-lg p-8 mb-8">
            <h2 class="font-bold text-2xl text-gray-800 mb-4">Who We Are</h2>
            <p class="text-gray-600 leading-relaxed mb-4">
                Welcome to <span class="font-bold text-friv-orange"><?= APP_NAME ?></span> — your ultimate destination for fun, rewarding, and competitive online gaming. We are a passionate team of developers and designers who believe that gaming should be accessible, exciting, and rewarding for everyone.
            </p>
            <p class="text-gray-600 leading-relaxed">
                Our platform lets you play a wide variety of games, rack up points, climb the leaderboard, and redeem your hard-earned points for amazing rewards. Whether you're a casual player or a competitive gamer, there's something here for you!
            </p>
        </div>

        <!-- Mission Section -->
        <div class="grid md:grid-cols-3 gap-6 mb-8">
            <div class="bg-friv-blue text-white rounded-2xl shadow-lg p-6 text-center">
                <div class="text-4xl mb-3">🎯</div>
                <h3 class="font-bold text-lg mb-2">Our Mission</h3>
                <p class="text-sm opacity-90">To create a fun, fair, and rewarding gaming experience for players of all ages.</p>
            </div>
            <div class="bg-friv-green text-white rounded-2xl shadow-lg p-6 text-center">
                <div class="text-4xl mb-3">🏆</div>
                <h3 class="font-bold text-lg mb-2">Our Vision</h3>
                <p class="text-sm opacity-90">To become the most loved casual gaming platform where every play counts.</p>
            </div>
            <div class="bg-friv-purple text-white rounded-2xl shadow-lg p-6 text-center">
                <div class="text-4xl mb-3">💡</div>
                <h3 class="font-bold text-lg mb-2">Our Values</h3>
                <p class="text-sm opacity-90">Fun, fairness, community, and continuous improvement in everything we do.</p>
            </div>
        </div>

        <!-- Team Section -->
        <div class="bg-white rounded-2xl shadow-lg p-8 mb-8">
            <h2 class="font-bold text-2xl text-gray-800 mb-6">Meet the Team</h2>
            <div class="grid md:grid-cols-4 gap-6">

                <!-- Team Member 1 -->
                <div class="text-center">
                    <img src="<?= asset('images/team/vargas.jpg') ?>" alt="Your Name"
                        class="w-20 h-20 rounded-full mx-auto mb-3 shadow-md object-cover border-4 border-friv-orange">
                    <h3 class="font-bold text-gray-800">Jerovi M. Vargas</h3>
                    <p class="text-sm text-friv-blue font-semibold">Lead Developer</p>
                    <p class="text-xs text-gray-500 mt-1">Building the platform from the ground up.</p>
                </div>

                <!-- Team Member 2 -->
                <div class="text-center">
                    <img src="<?= asset('images/team/krishelle.jpg') ?>" alt="Team Member"
                        class="w-20 h-20 rounded-full mx-auto mb-3 shadow-md object-cover border-4 border-friv-yellow">
                    <h3 class="font-bold text-gray-800">Krishelle S. Sobrevilia</h3>
                    <p class="text-sm text-friv-blue font-semibold">UI/UX Designer</p>
                    <p class="text-xs text-gray-500 mt-1">Crafting beautiful and intuitive experiences.</p>
                </div>

                <!-- Team Member 3 -->
                <div class="text-center">
                    <img src="<?= asset('images/team/rigo.jpg') ?>" alt="Team Member"
                        class="w-20 h-20 rounded-full mx-auto mb-3 shadow-md object-cover border-4 border-friv-green">
                    <h3 class="font-bold text-gray-800">John Rigo A. Gulmatico</h3>
                    <p class="text-sm text-friv-blue font-semibold">Game Designer</p>
                    <p class="text-xs text-gray-500 mt-1">Designing games that keep you coming back.</p>
                </div>

                <!-- Team Member 4 -->
                <div class="text-center">
                    <img src="<?= asset('images/team/member4.jpg') ?>" alt="Team Member"
                        class="w-20 h-20 rounded-full mx-auto mb-3 shadow-md object-cover border-4 border-friv-pink">
                    <h3 class="font-bold text-gray-800">Jomammie S. Solatorio</h3>
                    <p class="text-sm text-friv-blue font-semibold">Team Member</p>
                    <p class="text-xs text-gray-500 mt-1">Contributing to the team's success.</p>
                </div>

            </div>
        </div>

        <!-- Contact Section -->
        <div class="bg-white rounded-2xl shadow-lg p-8 mb-8">
            <h2 class="font-bold text-2xl text-gray-800 mb-6">Contact Us</h2>
            <div class="grid md:grid-cols-2 gap-8">

                <!-- Contact Info -->
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-friv-blue rounded-full flex items-center justify-center text-white text-lg">📧</div>
                        <div>
                            <p class="text-xs text-gray-400">Email</p>
                            <p class="font-semibold text-gray-700">vargasjerovi1c@gmail.com</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-friv-green rounded-full flex items-center justify-center text-white text-lg">📱</div>
                        <div>
                            <p class="text-xs text-gray-400">Phone</p>
                            <p class="font-semibold text-gray-700">09663519680</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-friv-purple rounded-full flex items-center justify-center text-white text-lg">📍</div>
                        <div>
                            <p class="text-xs text-gray-400">Location</p>
                            <p class="font-semibold text-gray-700">Central Philippines State University</p>
                        </div>
                    </div>
                </div>

                <!-- Contact Form -->
                <form action="<?= baseUrl('/contact-submit.php') ?>" method="POST" class="space-y-4">
                    <?php if (function_exists('csrfField')) echo csrfField(); ?>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1 font-semibold">Your Name</label>
                        <input type="text" name="name" required placeholder="Enter your name"
                            class="w-full border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-friv-blue">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1 font-semibold">Email Address</label>
                        <input type="email" name="email" required placeholder="Enter your email"
                            class="w-full border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-friv-blue">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1 font-semibold">Message</label>
                        <textarea name="message" required rows="4" placeholder="Write your message here..."
                            class="w-full border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-friv-blue resize-none"></textarea>
                    </div>
                    <button type="submit"
                        class="w-full bg-friv-blue text-white font-semibold py-2 rounded-lg hover:bg-blue-700 transition shadow">
                        Send Message 📨
                    </button>
                </form>

            </div> 
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>