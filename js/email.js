// js/email.js
const emailConfig = {
    smtp: {
        host: "smtp.gmail.com",
        port: 587,
        secure: false,
        auth: {
            user: "your-email@gmail.com",
            pass: "your-app-password"
        }
    },
    from: "David IGE <noreply@davidige.com>",
    adminEmail: "admin@davidige.com"
};

const emailTemplates = {
    // Contact form submission - to admin
    contactAdmin: (data) => ({
        subject: `New Contact Form Submission: ${data.subject}`,
        html: `
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #0c2039, #1a365d); color: white; padding: 30px; text-align: center; }
                    .content { padding: 30px; background: #f9fafc; }
                    .field { margin-bottom: 20px; }
                    .field-label { font-weight: bold; color: #1a365d; margin-bottom: 5px; }
                    .field-value { background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #b59410; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>New Contact Form Submission</h1>
                    </div>
                    <div class="content">
                        <div class="field">
                            <div class="field-label">Name:</div>
                            <div class="field-value">${data.name}</div>
                        </div>
                        <div class="field">
                            <div class="field-label">Email:</div>
                            <div class="field-value">${data.email}</div>
                        </div>
                        <div class="field">
                            <div class="field-label">Company:</div>
                            <div class="field-value">${data.company || 'Not provided'}</div>
                        </div>
                        <div class="field">
                            <div class="field-label">Subject:</div>
                            <div class="field-value">${data.subject}</div>
                        </div>
                        <div class="field">
                            <div class="field-label">Message:</div>
                            <div class="field-value">${data.message.replace(/\n/g, '<br>')}</div>
                        </div>
                        <div class="field">
                            <div class="field-label">Date:</div>
                            <div class="field-value">${new Date().toLocaleString()}</div>
                        </div>
                    </div>
                    <div class="footer">
                        <p>This message was sent from your portfolio website contact form.</p>
                        <p>© ${new Date().getFullYear()} David IGE - All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        `
    }),

    // Contact form submission - to user
    contactUser: (data) => ({
        subject: "Thank you for contacting David IGE",
        html: `
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #0c2039, #1a365d); color: white; padding: 30px; text-align: center; }
                    .content { padding: 30px; background: #f9fafc; }
                    .message { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
                    .button { display: inline-block; padding: 12px 30px; background: #b59410; color: white; text-decoration: none; border-radius: 5px; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>Thank You for Reaching Out</h1>
                    </div>
                    <div class="content">
                        <p>Dear ${data.name},</p>
                        <p>Thank you for contacting me through my portfolio website. I have received your message and will get back to you within 24-48 hours.</p>
                        
                        <div class="message">
                            <h3>Your Message:</h3>
                            <p><strong>Subject:</strong> ${data.subject}</p>
                            <p>${data.message.replace(/\n/g, '<br>')}</p>
                        </div>
                        
                        <p>In the meantime, you can:</p>
                        <ul>
                            <li>Connect with me on <a href="https://linkedin.com/in/davidige">LinkedIn</a></li>
                            <li>View my <a href="https://davidige.com/portfolio">portfolio projects</a></li>
                            <li>Download my <a href="https://davidige.com/cv">CV</a></li>
                        </ul>
                        
                        <p>Best regards,<br>
                        <strong>David IGE</strong><br>
                        Senior Product/Programmes Manager</p>
                    </div>
                    <div class="footer">
                        <p>© ${new Date().getFullYear()} David IGE. All rights reserved.</p>
                        <p>This is an automated response, please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        `
    }),

    // Request form submission - to admin
    requestAdmin: (data) => ({
        subject: `New Information Request: ${data.requestType}`,
        html: `
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #0c2039, #1a365d); color: white; padding: 30px; text-align: center; }
                    .content { padding: 30px; background: #f9fafc; }
                    .field { margin-bottom: 20px; }
                    .field-label { font-weight: bold; color: #1a365d; margin-bottom: 5px; }
                    .field-value { background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #b59410; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>New Information Request</h1>
                    </div>
                    <div class="content">
                        <div class="field">
                            <div class="field-label">Name:</div>
                            <div class="field-value">${data.name}</div>
                        </div>
                        <div class="field">
                            <div class="field-label">Email:</div>
                            <div class="field-value">${data.email}</div>
                        </div>
                        <div class="field">
                            <div class="field-label">Company:</div>
                            <div class="field-value">${data.company || 'Not provided'}</div>
                        </div>
                        <div class="field">
                            <div class="field-label">Position:</div>
                            <div class="field-value">${data.position || 'Not provided'}</div>
                        </div>
                        <div class="field">
                            <div class="field-label">Request Type:</div>
                            <div class="field-value">${data.requestType}</div>
                        </div>
                        <div class="field">
                            <div class="field-label">Details:</div>
                            <div class="field-value">${data.details.replace(/\n/g, '<br>')}</div>
                        </div>
                        <div class="field">
                            <div class="field-label">Date:</div>
                            <div class="field-value">${new Date().toLocaleString()}</div>
                        </div>
                    </div>
                    <div class="footer">
                        <p>This request was submitted from your portfolio website.</p>
                        <p>© ${new Date().getFullYear()} David IGE - All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        `
    }),

    // Request form submission - to user
    requestUser: (data) => ({
        subject: "Your Information Request - David IGE",
        html: `
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #0c2039, #1a365d); color: white; padding: 30px; text-align: center; }
                    .content { padding: 30px; background: #f9fafc; }
                    .message { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>Information Request Received</h1>
                    </div>
                    <div class="content">
                        <p>Dear ${data.name},</p>
                        <p>Thank you for your interest in my professional background. I have received your request for <strong>${data.requestType}</strong> and will provide the requested information shortly.</p>
                        
                        <div class="message">
                            <h3>Your Request Details:</h3>
                            <p>${data.details.replace(/\n/g, '<br>')}</p>
                        </div>
                        
                        <p>I typically respond to information requests within 24-48 hours with comprehensive documentation tailored to your specific needs.</p>
                        
                        <p>Best regards,<br>
                        <strong>David IGE</strong><br>
                        Senior Product/Programmes Manager</p>
                    </div>
                    <div class="footer">
                        <p>© ${new Date().getFullYear()} David IGE. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        `
    }),

    // Contact reply - admin to user
    contactReply: (data) => ({
        subject: `Re: ${data.originalSubject || 'Your Contact Form Submission'}`,
        html: `
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #0c2039, #1a365d); color: white; padding: 30px; text-align: center; }
                    .content { padding: 30px; background: #f9fafc; }
                    .reply-box { background: #f0f4f8; padding: 20px; border-radius: 8px; border-left: 4px solid #b59410; margin: 20px 0; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>Response to Your Inquiry</h1>
                    </div>
                    <div class="content">
                        <p>Dear ${data.name},</p>
                        
                        <div class="reply-box">
                            ${data.message.replace(/\n/g, '<br>')}
                        </div>
                        
                        <p>Please don't hesitate to reach out if you have any further questions.</p>
                        
                        <p>Best regards,<br>
                        <strong>David IGE</strong><br>
                        Senior Product/Programmes Manager</p>
                    </div>
                    <div class="footer">
                        <p>© ${new Date().getFullYear()} David IGE. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        `
    }),

    // Request reply - admin to user
    requestReply: (data) => ({
        subject: `Re: Your Information Request - ${data.requestType}`,
        html: `
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #0c2039, #1a365d); color: white; padding: 30px; text-align: center; }
                    .content { padding: 30px; background: #f9fafc; }
                    .reply-box { background: #f0f4f8; padding: 20px; border-radius: 8px; border-left: 4px solid #b59410; margin: 20px 0; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>Information Request Response</h1>
                    </div>
                    <div class="content">
                        <p>Dear ${data.name},</p>
                        
                        <p>Thank you for your patience. Regarding your request for <strong>${data.requestType}</strong>:</p>
                        
                        <div class="reply-box">
                            ${data.message.replace(/\n/g, '<br>')}
                        </div>
                        
                        <p>I've attached the relevant documents to this email. Please review them and let me know if you need any clarification or additional information.</p>
                        
                        <p>Best regards,<br>
                        <strong>David IGE</strong><br>
                        Senior Product/Programmes Manager</p>
                    </div>
                    <div class="footer">
                        <p>© ${new Date().getFullYear()} David IGE. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        `
    }),

    // Custom reply template
    customReply: (data) => ({
        subject: data.subject || "Message from David IGE",
        html: `
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #0c2039, #1a365d); color: white; padding: 30px; text-align: center; }
                    .content { padding: 30px; background: #f9fafc; }
                    .message-box { background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #b59410; margin: 20px 0; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>${data.header || 'Message from David IGE'}</h1>
                    </div>
                    <div class="content">
                        <p>Dear ${data.name},</p>
                        
                        <div class="message-box">
                            ${data.message.replace(/\n/g, '<br>')}
                        </div>
                        
                        <p>Best regards,<br>
                        <strong>David IGE</strong><br>
                        Senior Product/Programmes Manager</p>
                    </div>
                    <div class="footer">
                        <p>© ${new Date().getFullYear()} David IGE. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        `
    })
};

// Email sending function
async function sendEmail(template, to, data, attachments = []) {
    // This would integrate with your email service (SendGrid, AWS SES, etc.)
    console.log(`Sending email to ${to} using template: ${template}`);
    
    // Simulated email sending
    return new Promise((resolve) => {
        setTimeout(() => {
            console.log('Email sent successfully');
            resolve({ success: true, messageId: Date.now().toString() });
        }, 1000);
    });
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { emailConfig, emailTemplates, sendEmail };
}