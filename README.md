# 🔐 DropLocker - Secure P2P File Sharing

A secure, peer-to-peer, no-login file sharing tool with **C++ compression** and **web encryption**. Users can upload encrypted files, generate one-time links, and share them securely.

## ✨ Key Features

- **C++ Compression**: Advanced Huffman Coding & LZW algorithms for optimal file size reduction
- **Client-Side Encryption**: AES-256-CBC encryption for maximum security
- **One-Time Links**: Auto-delete after download or expiry
- **No Registration**: Completely anonymous file sharing
- **Modern UI**: Clean, responsive web interface
- **Auto-Cleanup**: Expired files are automatically removed

## 🛠️ Tech Stack

- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Backend**: PHP 8.0+, SQLite Database  
- **Compression**: C++ (Huffman & LZW algorithms)
- **Encryption**: OpenSSL (AES-256-CBC)
- **Server**: Apache/Nginx + PHP-FPM

## 🚀 Quick Start

1. **Upload to Free Hosting**: Use InfinityFree, AeonFree, or Wasmer
2. **Compile C++**: Run `make` in cpp/ directory
3. **Set Permissions**: Make uploads/ and database/ writable
4. **Test**: Upload a file and generate secure link!

## 📁 File Structure

