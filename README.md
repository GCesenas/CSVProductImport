# 📦 CSVProductImport

This Laravel command-line tool allows importing product data from a CSV file into a MySQL database. It validates and processes data with customizable business rules, skipping invalid or duplicate entries.

## ✨ Features

- Validates CSV file structure and content.
- Automatically detects CSV delimiter.
- Handles different file encodings and normalizes line endings.
- Allows importing data in a test mode without database insertion.
- Skips products with invalid or duplicate information.
- Provides detailed feedback during the import process.

## 🔧 Requirements

- PHP 8.x
- Composer
- MySQL database
- Laravel 9.x or later

## 💻 Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/GCesenas/CSVProductImport.git
   cd CSVProductImport
   
2. Install the dependencies:

     ```bash
    composer install

3. Set up the .env file:
Copy .env.example to .env and update the database credentials:

    cp .env.example .env

4. Run the database migrations:

     ```bash
    php artisan migrate

This will create the required table (tblProductData) in your database.

## ⚡ Usage

### Importing Product data

To import data from a CSV file, use the following command:

    php artisan import:product-data --file=stock.csv

Make sure the CSV file has the required headers: Product Code, Product Name, Product Description, Stock, Cost in GBP, Discontinued.

### Running in Test Mode

You can run the import in test mode to see what would happen without actually inserting data into the database:

    php artisan import:product-data --file=stock.csv --test

In test mode, the command will process the data, but no records will be inserted into the database.

### Sample CSV File

Your CSV file should have the following structure:

    Product Code,Product Name,Product Description,Stock,Cost in GBP,Discontinued
    P0011,TV,High-definition television,15,499.99,no
    P0012,CD Player,High-quality CD player,10,99.99,no
    P0013,VCR,VHS player,5,29.99,yes

## ✔️ Testing

### Running Unit and Feature Tests

The project includes unit and feature tests to ensure the correct behavior of the import functionality.

To run all tests:

    php artisan test

### Test Coverage

- **Unit Tests**: Test individual methods like cleanPrice, cleanStock, and detectDelimiter.

- **Feature Tests**: Test the complete import process, including valid data imports, handling of invalid rows, and detection of duplicates.

## 📈 Example Output

    php artisan import:product-data --file=stock.csv

    Importing data...
    Imported product: TV
    Imported product: CD Player
    Skipped product due to duplicate code: TV
    Skipped product due to excessive length in product name: Ultra-long product name
    Import complete. Processed: 5, Imported: 2, Skipped: 3
    The following errors occurred during the import:
    Skipped row due to column mismatch: P0011, Misc Cables, error   in export

## ⚠️ Common Issues

- **CSV File Not Found**: Ensure the file path is correct and the file exists.
- **Database Errors**: Check your .env database configuration if you encounter connection issues.
- **Encoding Issues**: The script automatically detects and converts file encoding to UTF-8, but ensure your CSV is in a supported format.

## 🔓 License

This project is open source and available under the MIT License. You are free to use, modify, and distribute the code in this project under the terms of the MIT License. This license grants you permission to use the software for any purpose, to distribute it, to modify it, and to distribute modified versions of the software, under the terms that the original copyright and license notice must be preserved.

## 🥷🏽 Author

Developed by Gerardo Ceseñas.