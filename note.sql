-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 02, 2025 lúc 03:16 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `note`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `notes`
--

CREATE TABLE `notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` longtext NOT NULL,
  `type` enum('note','folder') DEFAULT 'note',
  `parent_id` int(11) DEFAULT NULL,
  `expanded` tinyint(1) DEFAULT 1,
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `notes`
--

INSERT INTO `notes` (`id`, `user_id`, `title`, `content`, `type`, `parent_id`, `expanded`, `views`, `created_at`, `updated_at`) VALUES
(2, 1, 'Thư mục mới', '', 'folder', 4, 1, 1, '2025-10-31 02:43:42', '2025-11-01 12:45:25'),
(4, 1, 'Thư mục mới', '', 'folder', NULL, 1, 1, '2025-10-31 03:33:33', '2025-11-01 12:56:02'),
(6, 1, 'Ghi chú mới', '', 'note', 2, 1, 1, '2025-11-01 12:44:31', '2025-11-01 12:44:57'),
(7, 1, 'Ghi chú mới', '<p><br></p>', 'note', 4, 1, 1, '2025-11-01 12:45:07', '2025-11-01 12:51:32'),
(8, 1, 'Ghi chú mới', '<p>ádasdasd</p>', 'note', NULL, 1, 1, '2025-11-01 12:50:30', '2025-11-01 12:55:05'),
(9, 1, 'Ghi chú mới', '', 'note', NULL, 1, 1, '2025-11-02 13:09:17', '2025-11-02 13:09:17');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `shares`
--

CREATE TABLE `shares` (
  `id` varchar(10) NOT NULL,
  `note_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `shares`
--

INSERT INTO `shares` (`id`, `note_id`, `user_id`, `description`, `views`, `created_at`) VALUES
('gyZ491htCH', 8, 1, 'a', 1, '2025-11-01 12:55:03');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `short_links`
--

CREATE TABLE `short_links` (
  `id` varchar(10) NOT NULL,
  `original_url` text NOT NULL,
  `custom_alias` varchar(50) DEFAULT NULL,
  `clicks` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `short_links`
--

INSERT INTO `short_links` (`id`, `original_url`, `custom_alias`, `clicks`, `created_at`, `user_id`) VALUES
('a', 'http://localhost/phpmyadmin/index.php?route=/sql&pos=0&db=note&table=users', 'a', 0, '2025-10-31 02:35:02', 1),
('aa', 'http://localhost/note/shortener/', 'aa', 1, '2025-11-01 12:48:56', 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `uploaded_images`
--

CREATE TABLE `uploaded_images` (
  `id` varchar(10) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `status`, `created_at`) VALUES
(1, 'admin', '$2y$10$fbdeeqQpR.MhiMpi.j47S.GY6W4CmjrsiwYouAUSCkgJKMGyXXMPe', 'Administrator', 'admin', 'active', '2025-10-31 02:31:28');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `type` (`type`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Chỉ mục cho bảng `shares`
--
ALTER TABLE `shares`
  ADD PRIMARY KEY (`id`),
  ADD KEY `note_id` (`note_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Chỉ mục cho bảng `short_links`
--
ALTER TABLE `short_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `custom_alias` (`custom_alias`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Chỉ mục cho bảng `uploaded_images`
--
ALTER TABLE `uploaded_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role` (`role`),
  ADD KEY `status` (`status`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notes_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `notes` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `shares`
--
ALTER TABLE `shares`
  ADD CONSTRAINT `shares_ibfk_1` FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shares_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `short_links`
--
ALTER TABLE `short_links`
  ADD CONSTRAINT `short_links_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `uploaded_images`
--
ALTER TABLE `uploaded_images`
  ADD CONSTRAINT `uploaded_images_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
