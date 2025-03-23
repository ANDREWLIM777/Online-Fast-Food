const express = require('express');
const sqlite3 = require('sqlite3').verbose();
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const nodemailer = require('nodemailer');
const path = require('path');

const app = express();
const PORT = 3000;
const SECRET = 'your-secret-key'; // Change this in production!

// Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname, 'public')));

// SQLite Database Setup
const db = new sqlite3.Database('./customers.db', (err) => {
    if (err) console.error(err.message);
    console.log('Connected to SQLite database.');
});

db.run(`CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE,
    password TEXT,
    name TEXT,
    resetToken TEXT
)`);

// Nodemailer Setup (for password reset emails)
const transporter = nodemailer.createTransport({
    service: 'gmail', // Use your email provider
    auth: {
        user: 'your-email@gmail.com', // Replace with your email
        pass: 'your-app-password' // Replace with your app-specific password
    }
});

// Customer Routes
// Customer Registration
app.post('/customer/register', async (req, res) => {
    const { email, password, name } = req.body;
    const hashedPassword = await bcrypt.hash(password, 10);
    db.run(`INSERT INTO customers (email, password, name) VALUES (?, ?, ?)`, 
        [email, hashedPassword, name], (err) => {
        if (err) return res.status(400).send('Email already exists.');
        res.send('Registration successful!');
    });
});

// Customer Login
app.post('/customer/login', (req, res) => {
    const { email, password } = req.body;
    db.get(`SELECT * FROM customers WHERE email = ?`, [email], async (err, row) => {
        if (err || !row || !(await bcrypt.compare(password, row.password))) {
            return res.status(401).send('Invalid credentials.');
        }
        const token = jwt.sign({ id: row.id }, SECRET, { expiresIn: '1h' });
        res.json({ token, name: row.name });
    });
});

// Middleware to verify token
const authenticate = (req, res, next) => {
    const token = req.headers['authorization'];
    if (!token) return res.status(401).send('Access denied.');
    try {
        const decoded = jwt.verify(token.split(' ')[1], SECRET);
        req.user = decoded;
        next();
    } catch (err) {
        res.status(400).send('Invalid token.');
    }
};

// Edit Profile
app.put('/customer/profile', authenticate, (req, res) => {
    const { name } = req.body;
    db.run(`UPDATE customers SET name = ? WHERE id = ?`, [name, req.user.id], (err) => {
        if (err) return res.status(500).send('Error updating profile.');
        res.send('Profile updated!');
    });
});

// Change Password
app.put('/customer/change-password', authenticate, async (req, res) => {
    const { oldPassword, newPassword } = req.body;
    db.get(`SELECT password FROM customers WHERE id = ?`, [req.user.id], async (err, row) => {
        if (err || !(await bcrypt.compare(oldPassword, row.password))) {
            return res.status(401).send('Incorrect old password.');
        }
        const hashedPassword = await bcrypt.hash(newPassword, 10);
        db.run(`UPDATE customers SET password = ? WHERE id = ?`, [hashedPassword, req.user.id], (err) => {
            if (err) return res.status(500).send('Error changing password.');
            res.send('Password changed!');
        });
    });
});

// Reset Password Request
app.post('/customer/reset-password', (req, res) => {
    const { email } = req.body;
    db.get(`SELECT * FROM customers WHERE email = ?`, [email], (err, row) => {
        if (err || !row) return res.status(404).send('Email not found.');
        const resetToken = jwt.sign({ id: row.id }, SECRET, { expiresIn: '15m' });
        db.run(`UPDATE customers SET resetToken = ? WHERE id = ?`, [resetToken, row.id]);

        const mailOptions = {
            from: 'your-email@gmail.com',
            to: email,
            subject: 'Password Reset Request',
            text: `Click this link to reset your password: http://localhost:3000/customer/reset/${resetToken}`
        };
        transporter.sendMail(mailOptions, (err) => {
            if (err) return res.status(500).send('Error sending email.');
            res.send('Reset link sent to your email.');
        });
    });
});

// Reset Password Confirmation
app.post('/customer/reset/:token', (req, res) => {
    const { token } = req.params;
    const { newPassword } = req.body;
    db.get(`SELECT * FROM customers WHERE resetToken = ?`, [token], async (err, row) => {
        if (err || !row) return res.status(400).send('Invalid or expired token.');
        try {
            jwt.verify(token, SECRET);
            const hashedPassword = await bcrypt.hash(newPassword, 10);
            db.run(`UPDATE customers SET password = ?, resetToken = NULL WHERE id = ?`, 
                [hashedPassword, row.id], (err) => {
                if (err) return res.status(500).send('Error resetting password.');
                res.send('Password reset successful!');
            });
        } catch (err) {
            res.status(400).send('Invalid or expired token.');
        }
    });
});

// Serve Pages
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

app.get('/customer', (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'customer.html'));
});

app.listen(PORT, () => {
    console.log(`Server running on http://localhost:${PORT}`);
});