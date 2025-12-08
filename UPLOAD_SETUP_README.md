# File Upload Setup Instructions for Hosting

## Problema: "File exceeds upload_max_filesize directive in php.ini"

Kung nakikita mo ang error na ito sa hosting, sundin ang mga hakbang na ito:

---

## Solusyon 1: Gamit ang .htaccess (Recommended)

1. **I-upload ang `.htaccess` file** sa root directory ng hosting
2. Siguraduhing ang file ay naglalaman ng:
   ```apache
   php_value upload_max_filesize 10M
   php_value post_max_size 12M
   php_value max_execution_time 300
   php_value memory_limit 128M
   ```

3. Kung hindi gumagana, subukan ang alternative format:
   ```apache
   php_flag file_uploads On
   php_value upload_max_filesize 10485760
   php_value post_max_size 12582912
   ```

---

## Solusyon 2: Gamit ang php.ini

### Para sa Shared Hosting:

1. **Gumawa ng `php.ini` file** sa root directory
2. Lagyan ng:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 12M
   max_execution_time = 300
   memory_limit = 128M
   ```

3. **I-upload** ang file sa hosting

### Para sa cPanel Hosting:

1. Login sa **cPanel**
2. Pumunta sa **"Select PHP Version"** o **"MultiPHP INI Editor"**
3. I-edit ang mga settings:
   - `upload_max_filesize`: **10M**
   - `post_max_size`: **12M**
   - `max_execution_time`: **300**
   - `memory_limit`: **128M**

4. **Save Changes**

---

## Solusyon 3: Pag walang Access sa PHP Settings

Kung walang access sa PHP configuration (very restrictive hosting):

1. **Bawasan ang file size limit** sa client-side validation
2. I-edit ang `barangayportal.html`:
   - Line kung saan nakalagay ang `const maxSize = 10 * 1024 * 1024;`
   - Palitan ng mas maliit na value (e.g., `5 * 1024 * 1024` para sa 5MB)

3. I-update din ang `api/submissions.php`:
   - Line kung saan nakalagay ang `$maxSize = 10 * 1024 * 1024;`
   - Gawing same sa client-side

---

## Solusyon 4: Makipag-ugnayan sa Hosting Support

Kung wala pa ring gumagana, **contact hosting support** at hilingin na:

1. Taasan ang `upload_max_filesize` to **10M**
2. Taasan ang `post_max_size` to **12M**
3. Taasan ang `max_execution_time` to **300 seconds**

---

## Check Current PHP Settings

Gumawa ng test file para makita ang current PHP configuration:

1. **Gumawa ng file**: `phpinfo.php`
2. **Content**:
   ```php
   <?php
   phpinfo();
   ?>
   ```

3. **I-upload** sa hosting
4. **Bisitahin**: `http://your-domain.com/phpinfo.php`
5. **Hanapin** ang:
   - `upload_max_filesize`
   - `post_max_size`
   - `max_execution_time`

6. **IMPORTANTE**: Tanggalin ang `phpinfo.php` pagkatapos para sa security!

---

## Folder Permissions

Siguraduhing ang **`uploads/` folder** ay may tamang permissions:

```
uploads/ - 755 or 775
```

### Paano i-set ang permissions (via cPanel File Manager):

1. Right-click ang **uploads** folder
2. Piliin **"Change Permissions"**
3. I-set to **755** o **775**
4. Click **"Change Permissions"**

### Via FTP Client (FileZilla):

1. Right-click ang **uploads** folder
2. Piliin **"File Permissions"**
3. I-check:
   - Owner: Read, Write, Execute
   - Group: Read, Execute
   - Public: Read, Execute
4. Numeric value: **755** o **775**

---

## Troubleshooting

### Error: "No file provided"
- **Dahilan**: File ay masyadong malaki at hindi na-process ng PHP
- **Solusyon**: Sundin ang Solusyon 1, 2, o 3 sa taas

### Error: "Failed to write file to disk"
- **Dahilan**: Walang write permission ang uploads folder
- **Solusyon**: I-check ang folder permissions (755 or 775)

### Error: "Missing temporary folder on server"
- **Dahilan**: Walang temp directory para sa uploads
- **Solusyon**: Contact hosting support

---

## Testing

Pagkatapos ng setup:

1. **I-reload** ang barangay portal
2. **Subukang mag-upload** ng:
   - Small file (1-2MB) - dapat gumana
   - Medium file (5-7MB) - dapat gumana
   - Large file (9-10MB) - dapat gumana
   - Too large file (>10MB) - dapat mag-show ng error message

3. Kung may error pa rin, tingnan ang:
   - Browser Console (F12)
   - PHP error logs sa hosting

---

## Contact Support Template

Kung kakailanganin mong mag-contact sa hosting support:

```
Subject: Request to Increase PHP Upload Limits

Hello,

I am developing a web application that requires uploading documents up to 10MB in size. 

Could you please increase the following PHP settings for my account:

- upload_max_filesize: 10M
- post_max_size: 12M
- max_execution_time: 300
- memory_limit: 128M

Or alternatively, enable .htaccess php_value directives for my account.

Thank you!
```

---

## Notes

- Ang system ay naka-set na sa **10MB maximum file size**
- Ang client-side validation ay mag-check ng file bago mag-upload
- Ang server-side validation ay may detailed error messages
- May backup `.htaccess` at `php.ini` files na included
