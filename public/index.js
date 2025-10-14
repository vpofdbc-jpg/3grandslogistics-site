<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>3 Grands Logistics - Portal Home</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-[Inter] min-h-screen flex flex-col items-center justify-center px-4">
  <div class="flex flex-col md:flex-row justify-center gap-8 max-w-6xl w-full mb-10">

    <!-- Portal Card -->
    <div class="bg-white shadow-2xl rounded-xl p-8 text-center w-full max-w-md">
      <h1 class="text-3xl font-extrabold text-gray-900 mb-1">3 Grands Logistics</h1>
      <p class="text-lg text-gray-600 mb-1">Staff & Client Portal</p>
      <p class="text-sm text-gray-400 mb-8 max-w-xs mx-auto">Seamless access to transportation management and administrative tools.</p>

      <div class="flex flex-col gap-4">
        <a href="track_shipment.html" title="Track any package using your tracking number">
          <button class="w-full py-3 rounded-lg font-bold text-white bg-blue-500 hover:bg-blue-600 shadow-md">📦 Track Shipment</button>
        </a>

        <a href="customer/login.html" title="Login if you're an existing customer">
          <button class="w-full py-3 rounded-lg font-bold bg-gray-100 text-gray-700 hover:bg-gray-200">Customer Login</button>
        </a>

        <a href="customer/login.html" title="Create a new customer account">
          <button class="w-full py-3 rounded-lg font-bold bg-gray-100 text-gray-700 hover:bg-gray-200">New Customer Sign Up</button>
        </a>

        <a href="admin/login.html" title="Admin/staff panel access">
          <button class="w-full py-3 rounded-lg font-bold text-white bg-teal-700 hover:bg-teal-800 shadow-md">Admin / Staff Login</button>
        </a>
      </div>
    </div>

    <!-- Info Card -->
    <div class="bg-white shadow-2xl rounded-xl p-8 w-full max-w-xl text-left">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">Important Delivery Info</h2>
      <p class="text-gray-600 text-sm mb-4">Being present during delivery helps avoid theft, weather damage, and ensures correct handoff. Here's why it's important:</p>

      <ul class="list-disc pl-6 text-gray-600 text-sm mb-4 space-y-2">
        <li><strong class="text-gray-800">Preventing theft:</strong> Porch pirates can steal unattended packages.</li>
        <li><strong class="text-gray-800">Weather protection:</strong> Rain, snow, or heat may damage your delivery.</li>
        <li><strong class="text-gray-800">Inspect items:</strong> Check for damage before accepting.</li>
        <li><strong class="text-gray-800">Signature required:</strong> Some deliveries require in-person acceptance.</li>
        <li><strong class="text-gray-800">Solve issues:</strong> Address misdelivery or confusion on the spot.</li>
      </ul>

      <h3 class="text-lg font-semibold text-gray-800 mt-6 mb-2">If you're not home:</h3>
      <ul class="list-disc pl-6 text-gray-600 text-sm space-y-2">
        <li>Redelivery may be attempted.</li>
        <li>Package could be held at a facility.</li>
        <li>Eventually returned to sender if unclaimed.</li>
        <li>Theft/damage may not be covered after drop-off.</li>
      </ul>
    </div>
  </div>

  <footer class="text-sm text-gray-400 text-center">
    © 2025 3 Grands Logistics. All rights reserved.
    <a href="#" class="text-gray-500 hover:text-teal-700 ml-2">Contact Us</a> |
    <a href="#" class="text-gray-500 hover:text-teal-700 ml-2">Privacy Policy</a>
  </footer>
</body>
</html>
