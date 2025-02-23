<!-- about page that is there just to give some information about the purpose of the project -->
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>About</title>

        <!-- Connection to bootstrap, since I am using it for the interface -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

        <style>
            .banner {
                background-color: #661e0f;
                color: white;
                padding: 15px;
                font-size: 24px;
                font-weight: bold;
            }

            .footer {
                background-color: #333;
                color: white;
                padding: 5px 0;
                text-align: center;
                position: fixed;
                bottom: 0;
                width: 100%;
            }

            .main-content {
                min-height: 400px;
                display: flex;
                justify-content: center;
                align-items: center;
            }

            .content-wrapper {
                display: flex;
                justify-content: center;
                width: 100%;
            }

            .middle-content {
                max-width: 800px;
                width: 100%;
                background-color: #f8f9fa;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }

            .background-wrapper {
                background-image: url('uploads/bg1.jpg'); 
                background-size: cover; 
                background-repeat: no-repeat;
                background-attachment: fixed;
                min-height: 100vh; 
                overflow: hidden;
                color:black;
            }

            .btn-custom {
                margin: 10px;
                font-size: 16px;
                width: 100%;
            }

            .about-text {
                font-size: 18px;
                line-height: 1.6;
            }
        </style>
    </head>

    <body>

        <div class="banner">
            From-To
        </div>

        <!-- Since the user is not logged in, I want them to have the option only for the home and about pages -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="About.php">About</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="background-wrapper">
            <div class="main-content">
                <div class="content-wrapper">
                    <div class="middle-content">
                        <h2 class="text-center">About From-To</h2>
                        <p class="text-center about-text">
                            Welcome to <b>From-To</b>, a platform designed to connect clients who need goods delivered with deliverers who can provide transportation services.
                        </p>
                        <p class="text-center about-text">
                            <b>From-To</b> aims to simplify the logistics process. Clients can create orders specifying their needs (cargo type, weight, delivery destination), and deliverers can browse these orders to propose contracts, taking into account their available vehicle capacity.
                        </p>
                        <p class="text-center about-text">
                            The goal of the website is to allow individuals to get their items delivered by makng a connection between a client and deliverer. The platform offers interactions between the two, allowing them both to view and manage contracts, and haggle for the prices of delivery.
                        </p>
                        <p class="text-center about-text">
                            Whether you're a client or a deliverer, <b>From-To</b> provides a user-friendly environment to suit the needs of either one.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p style="font-size: 14px; margin-bottom: 0;">Anton Kovachev | © 2024</p>
        </div>

        <!-- I am not entirely sure how to use bootstrap, and if this is the correct way to connect, but it works, so i'm laeving it like that -->
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    </body>
</html>