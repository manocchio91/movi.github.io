output "ec2_public_ip" { value = aws_eip.app.public_ip }
output "rds_endpoint" { value = aws_db_instance.postgres.address }
output "s3_bucket" { value = aws_s3_bucket.data.id }
output "cloudwatch_log_group" { value = aws_cloudwatch_log_group.app.name }
output "media_url" { value = "https://${aws_cloudfront_distribution.media.domain_name}" }
