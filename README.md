# Translator Bot

This Bot is a PHP-based application that translates text from one language to another. It also provides information, definitions, and pronunciation of English words using Bing Translate AI and Word API.

## Features

- Translate text between multiple languages
- Retrieve definitions of English words
- Get pronunciation of English words
- Easy-to-use interface

## Installation

1. Clone the repository:
    ```bash
    git clone https://github.com/yourusername/translator-bot.git
    ```
2. Navigate to the project directory:
    ```bash
    cd translator-bot
    ```
3. Install dependencies:
    ```bash
    composer install
    ```

## Usage

1. Start the server:
    ```bash
    php -S localhost:8000
    ```
2. Open your browser and navigate to `http://localhost:8000`.

## Configuration

1. Create a `.env` file in the root directory and add your API keys or replace them in index/config file:
    ```plaintext
    BING_TRANSLATE_API_KEY=your_bing_translate_api_key
    WORD_API_KEY=your_word_api_key
    ```

## Acknowledgements

- Bing Translate AI
- Word API

