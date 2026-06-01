<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Approval</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600&display=swap');

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Open Sans', sans-serif;
      font-weight: 300;
      font-size: 14px;
      background: rgb(25, 199, 155);
      color: #272727;
      line-height: 1.6;
    }

    .container {
      max-width: 500px;
      margin: 50px auto;
      padding: 20px;
    }

    form#contact {
      background: #f9f9f9;
      padding: 30px;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    h1 {
      margin-bottom: 20px;
      font-size: 28px;
      font-weight: 600;
      text-align: center;
      color: #333;
    }

    fieldset {
      border: none;
      margin-bottom: 15px;
    }

    input,
    textarea {
      width: 100%;
      padding: 12px;
      border: 1px solid #ccc;
      background: #fff;
      border-radius: 4px;
      font-size: 14px;
      font-family: inherit;
      transition: border-color 0.3s ease-in-out;
    }

    input:focus,
    textarea:focus {
      border-color: rgb(25, 199, 155);
      outline: none;
    }

    textarea {
      resize: vertical;
      min-height: 100px;
    }

    button {
      width: 100%;
      padding: 12px;
      background: rgb(17, 146, 60);
      color: white;
      font-size: 18px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      transition: background 0.3s ease-in-out;
    }

    button:hover {
      background: rgb(15, 95, 42);
    }
  </style>
</head>

<body>
  <div class="container">
    <form id="contact" action="mail.php" method="post">
      <h1>Approval Sheet</h1>

      <fieldset>
        <input name="name" type="text" placeholder="Your Name" required />
      </fieldset>

      <fieldset>
        <input name="email" type="email" placeholder="Your Email Address" required />
      </fieldset>

      <fieldset>
        <input name="subject" type="text" placeholder="Subject" required />
      </fieldset>

      <fieldset>
        <textarea name="message" placeholder="Your Message..." required></textarea>
      </fieldset>

      <fieldset>
        <button type="submit" name="send" id="contact-submit">Send Message</button>
      </fieldset>
    </form>
  </div>
</body>

</html>
