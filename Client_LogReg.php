<!-- the page that shows if the user decides to proceed as a client from the main page -->
<?php 
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogIn or Registration</title>

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

        html, body {
            height: 100%; 
            margin: 0;
        }

        .navbar-nav {
            flex: 1;
            justify-content: flex-start; 
        }

        .main-content {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .content-wrapper {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        .middle-content {
            max-width: 600px; 
            width: 100%;
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .options ul {
            display: flex;
            justify-content: center;
            background-color: #f0f0f0;
            padding: 10px 0;
            margin-bottom: 20px;
        }

        .options li {
            list-style-type: none;
            padding: 10px 20px;
            cursor: pointer;
        }

        .options li.active {
            background-color: #04AA6D;
            color: white;
            font-weight: bold;
        }

        .field {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }

        .form-control {
            margin-bottom: 10px;
        }

        .background-wrapper {
            background-image: url('uploads/bg1.jpg'); 
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            color: black;
            overflow: hidden;
        }
    </style>
</head>

<body>
    <div class="banner">
        From-To Client View
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="About.php">About</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="background-wrapper">
        <div class="container-fluid">
            <div class="main-content">
                <div class="content-wrapper">
                    <div class="middle-content">
                        <div class="options">
                            <ul>
                                <li class="active" id="loginTab">LogIn</li>
                                <li id="registerTab">Register</li>
                            </ul>
                        </div>
                        <!-- the field for the forms, whether login or registration, depending on the user's input -->
                        <div class="field" id="formContainer">
                            <form action="ClientLogInCheck.php" method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" name="usernameCL" required id="username">
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" name="passwordCL" required id="password">
                                </div>
                                <button type="submit" class="btn btn-primary w-100">LogIn</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p style="font-size: 14px; margin-bottom: 0;">Anton Kovachev | © 2024</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <script>
        const loginTab = document.getElementById("loginTab");
        const registerTab = document.getElementById("registerTab");
        const formContainer = document.getElementById("formContainer");

        loginTab.addEventListener("click", showLogIn);
        registerTab.addEventListener("click", showRegister);

        //the functions that change the form according to the user's button presses
        function showLogIn() {
            loginTab.classList.add("active");
            registerTab.classList.remove("active");

            formContainer.innerHTML = `
                <form action="ClientLogInCheck.php" method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" name="usernameCL" required id="username">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" name="passwordCL" required id="password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">LogIn</button>
                </form>
            `;
        }

        function showRegister() {
            registerTab.classList.add("active");
            loginTab.classList.remove("active");

            formContainer.innerHTML = `
                <form action="ClientRegisterCheck.php" method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" name="usernameCR" required id="username">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" name="passwordCR" required id="password">
                    </div>
                    <div class="mb-3">
                        <label for="password2" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" name="passwordC2" required id="password2">
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" name="phoneC" id="phone">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="text" class="form-control" name="emailC" id="email">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Register</button>
                </form>
            `;
        }

        showLogIn();
    </script>

</body>
</html>