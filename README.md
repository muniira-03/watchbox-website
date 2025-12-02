# WatchBox 🎬

A simple PHP + MySQL web application to manage a personal movie watchlist.

Users can keep track of:
- Movies they want to watch
- Movies they already watched
- Personal ratings and notes
- Optional poster images

---

## ✨ Features

- Add / Edit / Delete movies (full **CRUD**)
- Mark movies as **Wishlist** or **Watched**
- Add a rating (1–10) and notes for each movie
- Poster upload support (image stored on the server, path stored in DB)
- Filter movies on the homepage:
  - All
  - Wishlist
  - Watched
- MySQL database integration

---

## 🛠️ Tech Stack

- **Backend:** PHP (MySQLi)
- **Database:** MySQL
- **Frontend:** HTML + CSS

---

## 🧩 Pages Overview

- `index.php`  
  Homepage – lists all movies, with filters and actions (Edit / Delete).

- `add_movie.php`  
  Form to add a new movie, validate input, and upload an optional poster.

- `edit_movie.php`  
  Load existing movie data, update any field, and change the poster.

- `delete_movie.php`  
  Confirm and delete a movie record, and remove its poster from the server.

- `config.php`  
  Database connection and setup helper (creates the database/table if needed).

---

## 🖥️ Demo

The demo includes:

- Homepage with movie table and filters  
- Add Movie form  
- Edit Movie form  
- Delete confirmation page  

You can run it locally with any stack like XAMPP / MAMP / WAMP.

---

## 🚀 How to Run Locally

1. Clone or download this repository into your web server directory (e.g. `htdocs`).
2. Make sure MySQL is running.
3. Update database credentials in `config.php` if needed.
4. Open your browser and go to:

   ```text
   http://localhost/your-folder-name/index.php

---

   ## 🏫 Designed as a university project 
