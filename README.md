# Asistente de Clima con IA

Un chatbot interactivo que utiliza inteligencia artificial para responder consultas sobre el clima, integrando datos en tiempo real de la API de Open-Meteo y almacenando el historial de las conversaciones.

## Características

- 🤖 Integración con OpenAI para procesamiento de lenguaje natural
- 🌤️ Datos del clima en tiempo real mediante Open-Meteo API
- 💬 Interfaz de chat intuitiva y responsive
- 📝 Historial de conversaciones
- 🎨 Diseño moderno con Tailwind CSS

## Requisitos

- PHP 8.2 o superior
- Composer
- Node.js y npm
- MySQL
- ApiKey de OpenAI

## Instalación

1. Clonar el repositorio:
   ```bash
   git clone https://github.com/Jesus-Gonzalez-Arroyo/CHAT_BOT_CLIMATE
   cd CHAT_BOT_CLIMATE
   ```

2. Instalar dependencias de PHP:
   ```bash
   composer install
   ```

3. Instalar dependencias de JavaScript:
   ```bash
   npm install
   ```

4. Copiar el archivo de configuración:
   ```bash
   cp .env.example .env
   ```

5. Generar la clave de aplicación:
   ```bash
   php artisan key:generate
   ```

6. Configurar el archivo .env con tus credenciales de base de datos y OpenAI:
   ```
   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=laravel
   DB_USERNAME=tu_usuario
   DB_PASSWORD=tu_contraseña

   OPENAI_API_KEY=tu_clave_api
   ```

7. Ejecutar las migraciones:
   ```bash
   php artisan migrate
   ```

8. Compilar los assets:
   ```bash
   npm run build
   ```

9. Iniciar el servidor:
   ```bash
   php artisan serve
   ```

La aplicación estará disponible en http://localhost:8000

## Estructura del Proyecto

- `app/Actions/` - Acciones reutilizables
- `app/DataTransferObjects/` - DTOs
- `app/Enums/` - Enumeraciones
- `app/Models/` - Modelos Eloquent
- `app/Services/` - Servicios (OpenAI, Weather)
- `resources/js/components/` - Componentes Vue
- `resources/views/` - Vistas Blade
- `database/migrations/` - Migraciones
- `routes/` - Definiciones de rutas
