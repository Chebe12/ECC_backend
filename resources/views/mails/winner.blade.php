<!DOCTYPE html>
<html>
<head>
    <title>Congratulations! You've won the Auction</title>
</head>
<body>
    <h2>🎉 Congratulations {{ $name }}!</h2>
    <p>You have been declared the winner of the auction: <strong>{{ $auctionTitle }}</strong>.</p>
    <p>Your bid of <strong>{{ $bidAmount }}</strong> was selected as the winning bid.</p>
    <p>We will contact you shortly with further details.</p>

    <br>
    <p>Thank you for participating!</p>
    <p>- The ECCA Auction Team</p>
</body>
</html>
