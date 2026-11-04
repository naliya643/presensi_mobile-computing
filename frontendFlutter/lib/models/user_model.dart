// ============================================================
// user_model.dart – Model data User
// Tugas: Merepresentasikan data user yang dikembalikan API login.
//        Menyediakan factory constructor untuk parse JSON.
// ============================================================

class UserModel {
  final int userId;
  final String nama;
  final String nim;

  const UserModel({
    required this.userId,
    required this.nama,
    required this.nim,
  });

  /// Parse dari JSON response API login
  /// Contoh JSON: {"user_id": 1, "nama": "Ahmad Fauzi", "nim": "2021001"}
  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      userId: json['user_id'] as int,
      nama: json['nama'] as String,
      nim: json['nim'] as String,
    );
  }

  /// Konversi ke Map untuk disimpan ke SharedPreferences
  Map<String, dynamic> toJson() => {
        'user_id': userId,
        'nama': nama,
        'nim': nim,
      };
}
