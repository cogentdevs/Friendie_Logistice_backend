<!DOCTYPE html>
<html>
<head>
    <title>New Message Notification</title>
</head>
<body style="font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #333;">
    <table align="center" width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; margin: 20px auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
        <tr>
            <td style="background-color: #c10037; color: #ffffff; text-align: center; padding: 20px;">
                <h2 style="margin: 0; font-size: 24px;">Friendie Logistics</h2>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px;">
                <p style="font-size: 16px; margin: 0 0 10px;">Dear <strong>{{ $data['to_name'] }}</strong>,</p>
                
                <h3 style="font-size: 18px; color: #c10037; margin: 10px 0;">New Message from {{ $data['from_name'] }}</h3>
                <h3 style="font-size: 18px; color: #c10037; margin: 10px 0;">For {{ $data['ad_title'] }}</h3>
                
                <p style="font-size: 16px; line-height: 1.6; margin: 10px 0;">
                    <strong>{{ $data['from_name'] }} : "{{ $data['message'] }}"</strong>
                </p>
                
                <div style="text-align: center; margin: 20px 0;">
                    <a href="https://friendielogistics.com/login" style="background-color: #c10037; color: #ffffff; text-decoration: none; font-size: 16px; padding: 10px 20px; border-radius: 5px; display: inline-block;">
                        View Message
                    </a>
                </div>

                <p style="font-size: 16px; line-height: 1.6; margin: 10px 0;">
                    A new message awaits your response. Please click the button above to view the message.
                </p>

                <p style="font-size: 16px; margin: 10px 0;">Regards,</p>
                <p style="font-size: 16px; font-weight: bold; margin: 0;">Friendie Logistics - Team</p>
            </td>
        </tr>
        <tr>
            <td style="background-color: #f4f4f4; text-align: center; padding: 10px; font-size: 12px; color: #666;">
                © {{ date('Y') }} Friendie Logistics. All rights reserved.
            </td>
        </tr>
    </table>
</body>
</html>
