<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $to = $_POST['to_email'];
    $from_name = $_POST['from_name'];
    $from_email = $_POST['from_email'];
    $message = wordwrap($_POST['message'], 70);
    $subject = "Message from $from_name via Alumni Directory";

    $headers = "From: $from_name <$from_email>";

    if (mail($to, $subject, $message, $headers)) {
        echo "<script>alert('Message sent successfully!'); window.history.back();</script>";
    } else {
        echo "<script>alert('Failed to send message.'); window.history.back();</script>";
    }
}
?>
