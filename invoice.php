<?php
include('include/initialise.php');

// Set price and and Expiry based on plan number
//if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Plan'])) {
  if (isset($_POST['Plan'])) {
  $_SESSION['Plan'] = $_POST['Plan']; // Grab plan number and add to session
} else {
  $functions->web_redirect("index.php"); //otherwise redirect to homepage
}

switch ($_SESSION['Plan']) {
  case "0":
    $_SESSION['Price'] = '0';
    $_SESSION['Plan_Desc'] = 'Free Trial';
    $d = strtotime("+1 week");
    $_SESSION['Expiry'] = date("Y-m-d", $d) . "T" . date("H:i:s", $d) . ".000Z";
    break;
  case "1":
    $_SESSION['Price'] = '2';
    $_SESSION['Plan_Desc'] = 'Bronze';
    $d = strtotime("+1 month");
    $_SESSION['Expiry'] = date("Y-m-d", $d) . "T" . date("H:i:s", $d) . ".000Z";
    break;
  case "2":
    $_SESSION['Price'] = '9';
    $_SESSION['Plan_Desc'] = 'Silver';
    $d = strtotime("+6 months");
    $_SESSION['Expiry'] = date("Y-m-d", $d) . "T" . date("H:i:s", $d) . ".000Z";
    break;
  case "3":
    $_SESSION['Price'] = '12';
    $_SESSION['Plan_Desc'] = 'Gold';
    $d = strtotime("+1 year");
    $_SESSION['Expiry'] = date("Y-m-d", $d) . "T" . date("H:i:s", $d) . ".000Z";
    break;
  default:
    break;
}

if ($coinFunctions->config['payment'] != '1' || $_SESSION['Plan'] == '0') {
  // Deal with the bots first
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['g-recaptcha_response'])) {
    // Build POST request:
    $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
    $recaptcha_response = $_POST['g-recaptcha_response'];
    $remoteip = $_SERVER["REMOTE_ADDR"];
    $action = $_POST['action'];

    // Curl Request
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $recaptcha_url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, array(
      'secret' => $coinFunctions->keys['captcha_secret_key'],
      'response' => $recaptcha_response,
      'remoteip' => $remoteip
    ));
    $curlData = curl_exec($curl);
    curl_close($curl);

    // Parse data
    $recaptcha = json_decode($curlData, true);
    if ($recaptcha["success"] == '1' && $recaptcha["action"] == $action && $recaptcha["score"] >= 0.5) {
      $verified = true;
    } else {
      $verified = false;
      exit (" Recaptcha thinks you're a bot! - please try again in a new tab.");
    }
  }
}

// Grab the next unused address 
$address = $coinFunctions->getColdStakingAddress("Hot");

  if (isset($address)) {
    $_SESSION['Address'] = $address;
  } else {
    print_r($address);
    echo "<br/>" . $url . "<br/>";
    exit(' Something went wrong checking the node! - please try again in a new tab it could just be a timeout.');
  }

  // Bypass payment for free trial otherwise take payment
  if ($_SESSION['Plan'] == '0' || $coinFunctions->config['payment'] != '1') {
    header('Location:' . 'activate.php');
    $functions->web_redirect("activate.php");
  } else {
    // Generate & store the InvoiceID in session
    $_SESSION['OrderID'] = 'CS-' . $coinFunctions->config['ticker'] . '-' . $_SESSION['Address'];
    // Full service description
    $serv = "Trustaking " . $_SESSION['Plan_Desc'] . " - Service Expiry: " . $_SESSION['Expiry'];
    // Create invoice
    $inv = $functions->CreateInvoice($_SESSION['OrderID'], $_SESSION['Price'], $serv);

    if (isset($inv['invoice_id'])) {
      $_SESSION['InvoiceID'] = $inv['invoice_id'];   // Store the InvoiceID in session
      $functions->web_redirect($inv['invoice_url']); // Redirect to payment page
    } else {
      echo "<br/>OrderID: " . $_SESSION['OrderID'] . "<br/>";
      echo "<br/>Price: " . $_SESSION['Price'] . "<br/>";
      echo "<br/>serv: " . $serv . "<br/>";
      echo "<br/>invoiceId: " . $_SESSION['InvoiceID'] . "<br/>";
      echo "<br/>invoiceURL: " . $inv['invoice_url'] . "<br/>";
    }
  }
?>