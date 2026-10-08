import '../services/api_client.dart';

/// Generic CRUD calls against an admin API resource path.
class ResourceService {
  final _api = ApiClient();

  Future<List<Map<String, dynamic>>> list(String path, {String? search}) async {
    final q = (search != null && search.trim().isNotEmpty)
        ? '?search=${Uri.encodeQueryComponent(search.trim())}'
        : '';
    final data = await _api.get('$path$q');
    return ((data as List?) ?? const [])
        .map((e) => (e as Map).cast<String, dynamic>())
        .toList();
  }

  Future<void> create(String path, Map<String, dynamic> body) => _api.post(path, body);

  Future<void> update(String path, Object id, Map<String, dynamic> body) =>
      _api.put('$path/$id', body);

  Future<void> remove(String path, Object id) => _api.delete('$path/$id');
}
