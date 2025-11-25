// The Firebase Admin SDK to access Firestore.
const admin = require("firebase-admin");

// The Cloud Functions for Firebase SDK components for 2nd Gen functions.
const functionsV2 = require("firebase-functions/v2");
const {onCall, HttpsError} = functionsV2.https;
const {setGlobalOptions} = functionsV2;
const {defineSecret} = functionsV2.params;

// Import the Stripe Node.js library.
const stripe = require("stripe");

// Initialize Firebase Admin SDK if not already initialized
if (admin.apps.length === 0) {
  admin.initializeApp();
}

// Define the Stripe secret key as a secret parameter.
// This tells Firebase that your function needs access to a secret
// named "STRIPE_SECRET_KEY".
const STRIPE_SECRET_KEY = defineSecret("STRIPE_SECRET_KEY");

// Set global options for 2nd gen functions.
setGlobalOptions({
  maxInstances: 10,
  // Use us-east4 to match your App Hosting backend region
  region: "us-east4",
});

/**
 * Creates a Stripe Checkout Session for a new subscription.
 * Callable from client-side via Firebase SDK.
 */
exports.createStripeCheckoutSession = onCall(
    // Configure this specific function to have access
    // to the STRIPE_SECRET_KEY.
    {secrets: [STRIPE_SECRET_KEY]},
    async (request) => {
    // In 2nd Gen callable functions, authentication info
    // is in request.auth
      if (!request.auth) {
        throw new HttpsError(
            "unauthenticated",
            "The function must be called while authenticated.",
        );
      }

      const userId = request.auth.uid;
      // Your VWC Subscription Price ID
      const priceId = "price_1SWrWxAuq4Y1kCpx0CRHPpH3";

      // Initialize Stripe with the secret key from the environment.
      const stripeClient = stripe(STRIPE_SECRET_KEY.value());

      try {
      // 2. Create the Checkout Session
        const session = await stripeClient.checkout.sessions.create({
          mode: "subscription", // Create a recurring subscription
          line_items: [
            {price: priceId, quantity: 1},
          ],
          // Ensure these URLs are valid and point to your web app.
          // They must be absolute, matching your Firebase Hosting domain.
          success_url: `https://grandslogistics-8af41.web.app/success` +
                     `?session_id={CHECKOUT_SESSION_ID}`,
          cancel_url: `https://grandslogistics-8af41.web.app/cancel`,
          // Link this session to your Firebase user
          client_reference_id: userId,
          metadata: {
          // Pass Firebase UID in metadata for webhooks
            firebaseUid: userId,
          },
        // customer_email: request.auth.token.email,
        // Uncomment the line above to prefill email if desired.
        }); // Removed the extra semicolon here

        // 3. Return the session ID and URL to the client
        return {
          sessionId: session.id,
          url: session.url,
        };
      } catch (error) {
        console.error("Error creating Stripe Checkout Session:", error);
        throw new HttpsError(
            "internal",
            "Unable to create Stripe Checkout Session.",
            error.message,
        );
      }
    },
);
