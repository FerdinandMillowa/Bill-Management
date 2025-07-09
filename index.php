<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BillMgtSytem</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.2.1/css/fontawesome.min.css">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="css/index.css">
</head>

<body>
  <header>
    <?php include 'header.php'; ?>
  </header>

  <main>
    <section class="event">
      <p>Select an option from the navigation menu or the tabs below to get started</p>

      <div class="row">
        <a href="add-bill.html">
          <div class="event-col">
            <h3>Add Bill</h3>
            <p>Lorem ipsum dolor, sit amet consectetur adipisicing elit. Dolor omnis maiores laborum, impedit cumque aperiam cum dicta consequuntur doloremque a, debitis odio fugit sapiente adipisci provident voluptatibus quam similique unde!</p>
            <img class="event-image" src="images/KaraokeNight.jpg" alt="Karaoke Nights">
          </div>
        </a>
        <a href="add-customer.php">
          <div class="event-col">
            <h3>Add Customer</h3>
            <p>Lorem ipsum dolor, sit amet consectetur adipisicing elit. Dolor omnis maiores laborum, impedit cumque aperiam cum dicta consequuntur doloremque a, debitis odio fugit sapiente adipisci provident voluptatibus quam similique unde!</p>
            <img class="event-image" src="images/DiscoNight.jpg" alt="Disco Nights ">
          </div>
        </a>
        <a href="add-payment.html">
          <div class="event-col">
            <h3>Add Payment</h3>
            <p>Lorem ipsum dolor, sit amet consectetur adipisicing elit. Dolor omnis maiores laborum, impedit cumque aperiam cum dicta consequuntur doloremque a, debitis odio fugit sapiente adipisci provident voluptatibus quam similique unde!</p>
            <img class="event-image" src="images/TriviaNight.jpg" alt="Trivia Nights">
          </div>
        </a>
      </div>
    </section>

    <section class="footer">
      <h4>About Us</h4>
      <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Laudantium ipsam aut dolores iste voluptatem numquam id amet, <br>non sapiente quod, ad a, maxime accusantium praesentium! Pariatur quia exercitationem facere possimus?</p>
      <div class="icons">
        <i class="fa fa-facebook"></i>
        <i class="fa fa-twitter"></i>
        <i class="fa fa-instagram"></i>
      </div>
      <p>Made with <i class="fa fa-heart-o"></i> by Ferdinand Millowa</p>
    </section>
  </main>
  <script src="js/script.js"></script>

</body>

</html>