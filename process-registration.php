<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $zipcode = $_POST['zipcode'] ?? '';

    $to = "3grandslogistics@gmail.com";
    $subject = "New Customer Account Registration";
    $message = "
    Username: $username
    Email: $email
    First Name: $firstName
    Last Name: $lastName
    Phone: $phone
    Address: $address
    City: $city
    State: $state
    Zipcode: $zipcode
    ";
    $headers = "From: no-reply@3grandslogistics.com";

    if (mail($to, $subject, $message, $headers)) {
        echo "Thank you! Your account request has been sent.";
    } else {
        echo "Sorry, there was an error sending your registration.";
    }
}
?>
