variable "environment" { type = string }
variable "aws_region" {
  type    = string
  default = "eu-south-1"
}
variable "availability_zones" {
  type    = list(string)
  default = ["eu-south-1a", "eu-south-1b"]
}
variable "admin_cidr" {
  type        = string
  description = "Single trusted SSH CIDR, e.g. 203.0.113.10/32"
}
variable "ssh_key_name" { type = string }
variable "db_name" {
  type    = string
  default = "eventi"
}
variable "db_username" {
  type    = string
  default = "eventi"
}
variable "db_password" {
  type      = string
  sensitive = true
}
variable "ec2_instance_type" {
  type    = string
  default = "t4g.small"
}
variable "rds_instance_class" {
  type    = string
  default = "db.t4g.micro"
}
