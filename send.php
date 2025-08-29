<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $empfaenger = "info@ci-dienstleistungen.com"; // Zieladresse
    $betreff = "Neue Kontaktanfrage von der Website";

    $name = htmlspecialchars($_POST["name"]);
    $adresse = htmlspecialchars($_POST["address"]);
    $telefon = htmlspecialchars($_POST["phone"]);
    $email = htmlspecialchars($_POST["email"]);
    $nachricht = htmlspecialchars($_POST["message"]);

    $inhalt = "Name: $name\n";
    $inhalt .= "Adresse: $adresse\n";
    $inhalt .= "Telefon: $telefon\n";
    $inhalt .= "E-Mail: $email\n\n";
    $inhalt .= "Nachricht:\n$nachricht\n";

    $header = "From: $email\r\n";
    $header .= "Reply-To: $email\r\n";

    if (mail($empfaenger, $betreff, $inhalt, $header)) {
        header("Location: index.html?ok=1#kontakt");
        exit;
    } else {
        header("Location: index.html?ok=0#kontakt");
        exit;
    }
}
?>
