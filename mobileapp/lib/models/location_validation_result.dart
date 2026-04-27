class LocationValidationResult {
  final bool isValid;
  final double currentDistance;
  final double allowedRadius;
  final String locationName;
  final String message;
  final String? suggestion;

  LocationValidationResult({
    required this.isValid,
    required this.currentDistance,
    required this.allowedRadius,
    required this.locationName,
    required this.message,
    this.suggestion,
  });

  factory LocationValidationResult.fromJson(Map<String, dynamic> json) {
    return LocationValidationResult(
      isValid: json['is_valid'] ?? false,
      currentDistance: (json['current_distance'] ?? 0.0).toDouble(),
      allowedRadius: (json['allowed_radius'] ?? 0.0).toDouble(),
      locationName: json['location_name'] ?? 'Lokasi Sekolah',
      message: json['message'] ?? 'Validasi lokasi gagal',
      suggestion: json['suggestion'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'is_valid': isValid,
      'current_distance': currentDistance,
      'allowed_radius': allowedRadius,
      'location_name': locationName,
      'message': message,
      'suggestion': suggestion,
    };
  }

  @override
  String toString() {
    return 'LocationValidationResult(isValid: $isValid, currentDistance: ${currentDistance}m, allowedRadius: ${allowedRadius}m, locationName: $locationName)';
  }
}
